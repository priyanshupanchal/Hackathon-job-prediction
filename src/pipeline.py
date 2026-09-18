"""
src/pipeline.py
================================================================================
MERGED PIPELINE — Hackathon Success & Career Readiness Prediction System
================================================================================

This single file merges all src/ modules into one self-contained script:
  • generate_dataset.py  — synthetic dataset generation
  • features.py          — feature engineering
  • preprocessing.py     — data loading, cleaning, and sklearn pipeline
  • evaluate.py          — evaluation metrics (binary & multi-class)
  • explain.py           — model explainability (SHAP / built-in fallback)
  • train.py             — model training, cross-validation, hyperparameter tuning
  • predict.py           — inference entry point for the Streamlit app

Run the full end-to-end pipeline:
    python src/pipeline.py

Or import individual symbols from notebooks / the Streamlit app:
    from pipeline import predict_student, CAREER_TRACK_LABELS

================================================================================
DATA INTEGRITY NOTICE:
    The dataset is 100% SYNTHETIC. DO NOT present results as real-world
    evidence of hackathon impact on employability. Swap in a real anonymised
    dataset (matching column names) and the pipeline needs no other changes.
================================================================================
"""

from __future__ import annotations

import json
import os
import time

import joblib
import numpy as np
import pandas as pd

from sklearn.compose import ColumnTransformer
from sklearn.ensemble import RandomForestClassifier
from sklearn.impute import SimpleImputer
from sklearn.inspection import permutation_importance
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import (
    accuracy_score,
    average_precision_score,
    classification_report,
    confusion_matrix,
    f1_score,
    precision_score,
    recall_score,
    roc_auc_score,
)
from sklearn.model_selection import (
    RandomizedSearchCV,
    StratifiedKFold,
    cross_validate,
    train_test_split,
)
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder, StandardScaler
from sklearn.tree import DecisionTreeClassifier

try:
    import shap
    SHAP_AVAILABLE = True
except ImportError:
    SHAP_AVAILABLE = False


# ==============================================================================
# PATHS & GLOBAL SETTINGS
# ==============================================================================

PROJECT_ROOT  = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
RAW_DATA_PATH = os.path.join(PROJECT_ROOT, "data", "raw", "student_employability.csv")
PROCESSED_DIR = os.path.join(PROJECT_ROOT, "data", "processed")
MODELS_DIR    = os.path.join(PROJECT_ROOT, "models")
PIPELINE_PATH = os.path.join(MODELS_DIR, "preprocessing_pipeline.joblib")

RANDOM_STATE = 42

ID_COLUMNS     = ["student_id"]
TARGET_COLUMNS = ["job_ready", "career_track"]

CATEGORICAL_FEATURES = ["degree"]

BASE_NUMERIC_FEATURES = [
    "age", "cgpa", "python_score", "sql_score", "statistics_score",
    "ml_score", "dl_score", "genai_score", "ml_projects",
    "end_to_end_projects", "deployed_projects", "kaggle_competitions",
    "best_competition_rank", "hackathons_attended", "hackathons_won",
    "finalist_status", "github_projects", "internship_months",
    "certifications", "ml_interview_score", "communication_score",
    "dsa_score", "resume_score", "mock_interview_score",
]

ENGINEERED_FEATURE_NAMES = [
    "hackathon_win_rate",
    "hackathon_finalist_rate",
    "has_won_hackathon",
    "deployment_rate",
    "end_to_end_rate",
    "has_deployed_project",
    "has_competed_kaggle",
    "competition_rank_missing",
    "avg_core_skill_score",
    "real_world_exposure",
    "avg_interview_readiness",
]

NUMERIC_FEATURES = BASE_NUMERIC_FEATURES + ENGINEERED_FEATURE_NAMES

CAREER_TRACK_LABELS = {
    0: "Data Analyst",
    1: "Data Scientist",
    2: "ML Engineer",
    3: "Deep Learning Engineer",
    4: "GenAI Engineer",
    5: "Not Yet Ready",
}


# ==============================================================================
# SECTION 1 — DATASET GENERATION  (generate_dataset.py)
# ==============================================================================

def generate_dataset(n: int = 1200, seed: int = RANDOM_STATE) -> pd.DataFrame:
    """
    Generates a SYNTHETIC student employability dataset.

    Design choice (built into the generator): fundamentals (ML/DL/SQL/Stats),
    end-to-end + deployed projects, internship months, and interview scores
    carry MUCH higher weight than hackathons_attended. hackathons_won gets a
    small positive nudge. Noise is added so no single feature is deterministic.
    """
    rng = np.random.default_rng(seed)

    degrees = rng.choice(
        ["B.Tech", "B.Sc", "M.Tech", "MCA", "BCA"],
        size=n,
        p=[0.55, 0.15, 0.10, 0.12, 0.08],
    )

    age  = rng.integers(20, 26, size=n)
    cgpa = np.clip(rng.normal(7.5, 0.9, size=n), 5.0, 10.0).round(2)

    # Skill scores (0-10)
    python_score     = np.clip(rng.normal(6.5, 1.8, size=n), 0, 10).round(1)
    sql_score        = np.clip(rng.normal(5.8, 2.0, size=n), 0, 10).round(1)
    statistics_score = np.clip(rng.normal(5.5, 2.0, size=n), 0, 10).round(1)
    ml_score         = np.clip(rng.normal(5.8, 2.0, size=n), 0, 10).round(1)
    dl_score         = np.clip(rng.normal(4.5, 2.2, size=n), 0, 10).round(1)
    genai_score      = np.clip(rng.normal(5.0, 2.2, size=n), 0, 10).round(1)

    # Project & competition evidence
    ml_projects           = rng.poisson(2.5, size=n)
    end_to_end_projects   = np.minimum(rng.poisson(1.2, size=n), ml_projects)
    deployed_projects     = np.minimum(rng.poisson(0.6, size=n), end_to_end_projects)
    kaggle_competitions   = rng.poisson(1.5, size=n)
    best_competition_rank = np.where(
        kaggle_competitions > 0,
        rng.integers(1, 500, size=n),
        np.nan,
    )
    github_projects = rng.poisson(4, size=n)

    # Hackathon evidence
    hackathons_attended = rng.poisson(3, size=n)
    hackathons_won      = np.minimum(
        rng.binomial(hackathons_attended, 0.15), hackathons_attended
    )
    finalist_status = np.minimum(
        rng.binomial(hackathons_attended, 0.25) + hackathons_won,
        hackathons_attended,
    )

    # Real-world exposure
    internship_months = rng.poisson(2, size=n)
    certifications    = rng.poisson(2.5, size=n)

    # Interview / soft-skill signals
    ml_interview_score   = np.clip(rng.normal(5.5, 2.0, size=n), 0, 10).round(1)
    communication_score  = np.clip(rng.normal(6.5, 1.8, size=n), 0, 10).round(1)
    dsa_score            = np.clip(rng.normal(5.5, 2.0, size=n), 0, 10).round(1)
    resume_score         = np.clip(rng.normal(6.0, 1.8, size=n), 0, 10).round(1)
    mock_interview_score = np.clip(rng.normal(5.8, 2.0, size=n), 0, 10).round(1)

    # Ground-truth job readiness score
    score = (
        1.1 * python_score
        + 0.9 * sql_score
        + 0.8 * statistics_score
        + 1.5 * ml_score
        + 0.9 * dl_score
        + 0.6 * genai_score
        + 2.2 * end_to_end_projects
        + 2.8 * deployed_projects
        + 0.6 * ml_projects
        + 1.1 * kaggle_competitions
        + 2.4 * internship_months
        + 0.3 * certifications
        + 1.8 * ml_interview_score
        + 1.1 * communication_score
        + 0.9 * dsa_score
        + 0.7 * resume_score
        + 1.6 * mock_interview_score
        + 0.08 * hackathons_attended    # small weight
        + 0.50 * hackathons_won         # slightly more weight
        + 0.15 * finalist_status
        + rng.normal(0, 3.0, size=n)   # noise — not deterministic
    )
    prob_ready = 1 / (1 + np.exp(-(score - np.median(score)) / 5))
    job_ready  = (rng.random(n) < prob_ready).astype(int)

    # Career track label (5 = Not Yet Ready when job_ready == 0)
    track_scores = np.vstack([
        (sql_score + statistics_score) / 2,                             # 0 Data Analyst
        (statistics_score + ml_score + python_score) / 3,              # 1 Data Scientist
        (ml_score + end_to_end_projects * 2 + deployed_projects * 2),  # 2 ML Engineer
        (dl_score * 1.5 + ml_score) / 2,                               # 3 DL Engineer
        (genai_score * 1.5 + python_score) / 2,                        # 4 GenAI Engineer
    ]).T
    track_scores = (track_scores - track_scores.mean(axis=0)) / track_scores.std(axis=0)
    track_scores = track_scores + rng.normal(0, 0.6, size=track_scores.shape)
    best_track   = track_scores.argmax(axis=1)
    career_track = np.where(job_ready == 1, best_track, 5)

    df = pd.DataFrame({
        "student_id":            [f"S{100000 + i}" for i in range(n)],
        "age":                   age,
        "degree":                degrees,
        "cgpa":                  cgpa,
        "python_score":          python_score,
        "sql_score":             sql_score,
        "statistics_score":      statistics_score,
        "ml_score":              ml_score,
        "dl_score":              dl_score,
        "genai_score":           genai_score,
        "ml_projects":           ml_projects,
        "end_to_end_projects":   end_to_end_projects,
        "deployed_projects":     deployed_projects,
        "kaggle_competitions":   kaggle_competitions,
        "best_competition_rank": best_competition_rank,
        "hackathons_attended":   hackathons_attended,
        "hackathons_won":        hackathons_won,
        "finalist_status":       finalist_status,
        "github_projects":       github_projects,
        "internship_months":     internship_months,
        "certifications":        certifications,
        "ml_interview_score":    ml_interview_score,
        "communication_score":   communication_score,
        "dsa_score":             dsa_score,
        "resume_score":          resume_score,
        "mock_interview_score":  mock_interview_score,
        "job_ready":             job_ready,
        "career_track":          career_track,
    })

    # Introduce realistic missing values (~3% per column)
    for col in ["cgpa", "communication_score", "certifications", "resume_score"]:
        mask = rng.random(n) < 0.03
        df.loc[mask, col] = np.nan

    return df


# ==============================================================================
# SECTION 2 — FEATURE ENGINEERING  (features.py)
# ==============================================================================

def add_engineered_features(df: pd.DataFrame) -> pd.DataFrame:
    """
    Adds derived features that are more informative than raw counts alone.
    Applied BEFORE the ColumnTransformer to avoid train/serve skew.
    Returns a NEW DataFrame (does not mutate the input).
    """
    df = df.copy()

    # Hackathon efficiency signals
    df["hackathon_win_rate"] = np.where(
        df["hackathons_attended"] > 0,
        df["hackathons_won"] / df["hackathons_attended"], 0.0,
    )
    df["hackathon_finalist_rate"] = np.where(
        df["hackathons_attended"] > 0,
        df["finalist_status"] / df["hackathons_attended"], 0.0,
    )
    df["has_won_hackathon"] = (df["hackathons_won"] > 0).astype(int)

    # Project depth signals
    df["deployment_rate"] = np.where(
        df["end_to_end_projects"] > 0,
        df["deployed_projects"] / df["end_to_end_projects"], 0.0,
    )
    df["end_to_end_rate"] = np.where(
        df["ml_projects"] > 0,
        df["end_to_end_projects"] / df["ml_projects"], 0.0,
    )
    df["has_deployed_project"] = (df["deployed_projects"] > 0).astype(int)

    # Competition exposure
    df["has_competed_kaggle"]   = (df["kaggle_competitions"] > 0).astype(int)
    df["competition_rank_missing"] = df["best_competition_rank"].isna().astype(int)

    # Aggregate skill index
    skill_cols = ["python_score", "sql_score", "statistics_score",
                  "ml_score", "dl_score", "genai_score"]
    df["avg_core_skill_score"] = df[skill_cols].mean(axis=1)

    # Real-world exposure index
    df["real_world_exposure"] = (
        df["internship_months"]
        + df["deployed_projects"] * 2
        + df["end_to_end_projects"]
    )

    # Interview readiness index
    interview_cols = ["ml_interview_score", "communication_score",
                      "dsa_score", "resume_score", "mock_interview_score"]
    df["avg_interview_readiness"] = df[interview_cols].mean(axis=1)

    return df


# ==============================================================================
# SECTION 3 — PREPROCESSING  (preprocessing.py)
# ==============================================================================

def load_raw_data(path: str = RAW_DATA_PATH) -> pd.DataFrame:
    """Loads the raw CSV. Raises FileNotFoundError if the file is absent."""
    return pd.read_csv(path)


def clean_data(df: pd.DataFrame) -> pd.DataFrame:
    """
    Basic cleaning: drop exact duplicate rows, clip score columns to [0, 10].
    Missing values are NOT dropped — the pipeline's imputers handle them.
    """
    df = df.copy()
    before = len(df)
    df = df.drop_duplicates(subset=[c for c in df.columns if c != "student_id"])
    if len(df) != before:
        print(f"Dropped {before - len(df)} duplicate rows")
    score_cols = [c for c in df.columns if c.endswith("_score")]
    for c in score_cols:
        df[c] = df[c].clip(0, 10)
    if "cgpa" in df.columns:
        df["cgpa"] = df["cgpa"].clip(0, 10)
    return df


def build_preprocessing_pipeline() -> ColumnTransformer:
    """
    Returns an UNFITTED ColumnTransformer:
      - numeric: median imputation + standard scaling
      - categorical: most-frequent imputation + one-hot encoding
    Must be fit only on training data to prevent leakage.
    """
    numeric_transformer = Pipeline(steps=[
        ("imputer", SimpleImputer(strategy="median")),
        ("scaler",  StandardScaler()),
    ])
    categorical_transformer = Pipeline(steps=[
        ("imputer", SimpleImputer(strategy="most_frequent")),
        ("onehot",  OneHotEncoder(handle_unknown="ignore")),
    ])
    return ColumnTransformer(transformers=[
        ("num", numeric_transformer,    NUMERIC_FEATURES),
        ("cat", categorical_transformer, CATEGORICAL_FEATURES),
    ])


def get_feature_columns(df: pd.DataFrame) -> pd.DataFrame:
    """Selects only the model input columns in a fixed order."""
    return df[NUMERIC_FEATURES + CATEGORICAL_FEATURES]


def run_preprocessing(test_size: float = 0.2, random_state: int = RANDOM_STATE):
    """
    End-to-end preprocessing:
      1. Load raw data  2. Clean  3. Feature engineering
      4. Train/test split (stratified on job_ready)
      5. Fit ColumnTransformer on TRAIN ONLY (no leakage)
      6. Save processed CSV + fitted pipeline

    Returns: X_train, X_test, y_job_train, y_job_test,
             y_track_train, y_track_test, preprocessor
    """
    os.makedirs(PROCESSED_DIR, exist_ok=True)
    os.makedirs(MODELS_DIR,    exist_ok=True)

    df             = load_raw_data()
    df             = clean_data(df)
    df             = add_engineered_features(df)
    X              = get_feature_columns(df)
    y_job_ready    = df["job_ready"]
    y_career_track = df["career_track"]

    (X_train, X_test,
     y_job_train, y_job_test,
     y_track_train, y_track_test) = train_test_split(
        X, y_job_ready, y_career_track,
        test_size=test_size,
        random_state=random_state,
        stratify=y_job_ready,
    )

    preprocessor = build_preprocessing_pipeline()
    preprocessor.fit(X_train)          # FIT ON TRAIN ONLY
    joblib.dump(preprocessor, PIPELINE_PATH)
    print(f"Saved preprocessing pipeline -> {PIPELINE_PATH}")

    train_df = X_train.copy()
    train_df["job_ready"]    = y_job_train.values
    train_df["career_track"] = y_track_train.values
    train_df["split"]        = "train"
    test_df  = X_test.copy()
    test_df["job_ready"]     = y_job_test.values
    test_df["career_track"]  = y_track_test.values
    test_df["split"]         = "test"

    processed_df   = pd.concat([train_df, test_df], axis=0).reset_index(drop=True)
    processed_path = os.path.join(PROCESSED_DIR, "processed_data.csv")
    processed_df.to_csv(processed_path, index=False)
    print(f"Saved processed data {processed_df.shape} -> {processed_path}")

    return (X_train, X_test,
            y_job_train, y_job_test,
            y_track_train, y_track_test,
            preprocessor)


# ==============================================================================
# SECTION 4 — EVALUATION  (evaluate.py)
# ==============================================================================

def evaluate_binary_classifier(model, X_test, y_test) -> dict:
    """
    Returns accuracy, precision, recall, F1, ROC-AUC, PR-AUC, and the
    confusion matrix for a fitted binary classifier.
    """
    y_pred  = model.predict(X_test)
    y_proba = (
        model.predict_proba(X_test)[:, 1]
        if hasattr(model, "predict_proba")
        else model.decision_function(X_test)
    )
    return {
        "accuracy":         accuracy_score(y_test, y_pred),
        "precision":        precision_score(y_test, y_pred, zero_division=0),
        "recall":           recall_score(y_test, y_pred, zero_division=0),
        "f1":               f1_score(y_test, y_pred, zero_division=0),
        "roc_auc":          roc_auc_score(y_test, y_proba),
        "pr_auc":           average_precision_score(y_test, y_proba),
        "confusion_matrix": confusion_matrix(y_test, y_pred).tolist(),
    }


def evaluate_multiclass_classifier(model, X_test, y_test) -> dict:
    """
    Returns accuracy, macro-F1, a full classification report (text),
    and the confusion matrix for a fitted multi-class classifier.
    """
    y_pred = model.predict(X_test)
    return {
        "accuracy":              accuracy_score(y_test, y_pred),
        "macro_f1":              f1_score(y_test, y_pred, average="macro", zero_division=0),
        "classification_report": classification_report(y_test, y_pred, zero_division=0),
        "confusion_matrix":      confusion_matrix(y_test, y_pred).tolist(),
    }


def error_analysis(model, X_test, y_test, original_df, top_n: int = 10) -> pd.DataFrame:
    """
    Returns the top_n most confidently wrong predictions so the team can
    inspect model errors alongside human-readable inputs.
    original_df must share the same row order as X_test / y_test.
    """
    y_pred     = model.predict(X_test)
    confidence = (
        model.predict_proba(X_test).max(axis=1)
        if hasattr(model, "predict_proba")
        else np.ones(len(y_pred))
    )
    errors = original_df.copy().reset_index(drop=True)
    errors["true_label"]      = np.array(y_test).reshape(-1)
    errors["predicted_label"] = y_pred
    errors["confidence"]      = confidence
    errors = errors[errors["true_label"] != errors["predicted_label"]]
    return errors.sort_values("confidence", ascending=False).head(top_n)


# ==============================================================================
# SECTION 5 — EXPLAINABILITY  (explain.py)
# ==============================================================================

def get_feature_names(preprocessor) -> list:
    """
    Recovers human-readable feature names after ColumnTransformer.
    Numeric names stay the same; categorical names become e.g.
    'degree_B.Tech', 'degree_MCA', etc.
    """
    output_features = []
    for name, transformer, columns in preprocessor.transformers_:
        if name == "num":
            output_features.extend(columns)
        elif name == "cat":
            onehot    = transformer.named_steps["onehot"]
            cat_names = onehot.get_feature_names_out(columns)
            output_features.extend(cat_names.tolist())
    return output_features


def global_feature_importance(
    model, X_test_transformed, y_test, feature_names,
    n_repeats: int = 10, random_state: int = RANDOM_STATE,
) -> pd.DataFrame:
    """
    Global feature importance via permutation importance (model-agnostic).
    Returns a DataFrame sorted by mean importance (highest first).
    """
    result = permutation_importance(
        model, X_test_transformed, y_test,
        n_repeats=n_repeats, random_state=random_state, n_jobs=-1,
    )
    return (
        pd.DataFrame({
            "feature":         feature_names,
            "importance_mean": result.importances_mean,
            "importance_std":  result.importances_std,
        })
        .sort_values("importance_mean", ascending=False)
        .reset_index(drop=True)
    )


def built_in_feature_importance(model, feature_names) -> pd.DataFrame | None:
    """
    Uses the model's own .feature_importances_ (tree models) or .coef_
    (linear models). Returns None if neither attribute is available.
    """
    if hasattr(model, "feature_importances_"):
        importances = model.feature_importances_
    elif hasattr(model, "coef_"):
        importances = (
            np.abs(model.coef_).mean(axis=0)
            if model.coef_.ndim > 1
            else np.abs(model.coef_)
        )
    else:
        return None

    return (
        pd.DataFrame({"feature": feature_names, "importance": importances})
        .sort_values("importance", ascending=False)
        .reset_index(drop=True)
    )


def explain_single_prediction(
    model, preprocessor, X_row_transformed, feature_names,
    background_data=None, top_n: int = 5,
):
    """
    Explains ONE prediction (a single transformed row).

    Uses SHAP when available (true local explanation); otherwise falls back
    to global built-in feature importance (clearly labelled as an approximation).

    Returns: (top_positive, top_negative, method_str)
    """
    if SHAP_AVAILABLE:
        try:
            explainer   = (
                shap.Explainer(model, background_data)
                if background_data is not None
                else shap.Explainer(model)
            )
            shap_values = explainer(X_row_transformed)
            values      = shap_values.values[0]
            if values.ndim > 1:
                pred_class = model.predict(X_row_transformed)[0]
                values     = values[:, pred_class]
            pairs        = sorted(zip(feature_names, values), key=lambda x: x[1], reverse=True)
            top_positive = [p for p in pairs if p[1] > 0][:top_n]
            top_negative = [p for p in pairs if p[1] < 0][-top_n:]
            return top_positive, top_negative, "shap"
        except Exception:
            pass

    importance_df = built_in_feature_importance(model, feature_names)
    if importance_df is None:
        return [], [], "unavailable"
    top_features = importance_df.head(top_n)
    top_positive = list(zip(top_features["feature"], top_features["importance"]))
    return top_positive, [], "global_importance_fallback"


# ==============================================================================
# SECTION 6 — MODEL TRAINING  (train.py)
# ==============================================================================

def get_binary_candidate_models() -> dict:
    return {
        "LogisticRegression": LogisticRegression(max_iter=1000, random_state=RANDOM_STATE),
        "DecisionTree":       DecisionTreeClassifier(max_depth=6, random_state=RANDOM_STATE),
        "RandomForest":       RandomForestClassifier(n_estimators=300, random_state=RANDOM_STATE),
    }


def get_multiclass_candidate_models() -> dict:
    return {
        "LogisticRegression": LogisticRegression(max_iter=1000, random_state=RANDOM_STATE),
        "DecisionTree":       DecisionTreeClassifier(max_depth=6, random_state=RANDOM_STATE),
        "RandomForest":       RandomForestClassifier(n_estimators=300, random_state=RANDOM_STATE),
    }


def transform_features(preprocessor, X_train, X_test):
    """Applies the already-fitted preprocessing pipeline to train and test sets."""
    return preprocessor.transform(X_train), preprocessor.transform(X_test)


def cross_validate_models(
    models: dict, X, y, scoring: list, cv_folds: int = 5,
) -> pd.DataFrame:
    """
    Runs Stratified K-Fold cross-validation for every candidate model.
    Returns a DataFrame sorted by the first metric (descending).
    """
    cv   = StratifiedKFold(n_splits=cv_folds, shuffle=True, random_state=RANDOM_STATE)
    rows = []
    for name, model in models.items():
        print(f"  Cross-validating: {name}")
        start  = time.time()
        scores = cross_validate(
            model, X, y,
            cv=cv, scoring=scoring, n_jobs=-1, error_score="raise",
        )
        elapsed = time.time() - start
        row = {"model": name, "fit_time_sec": round(elapsed, 2)}
        for metric in scoring:
            row[f"{metric}_mean"] = np.mean(scores[f"test_{metric}"])
            row[f"{metric}_std"]  = np.std(scores[f"test_{metric}"])
        rows.append(row)
    primary = f"{scoring[0]}_mean"
    return (
        pd.DataFrame(rows)
        .sort_values(primary, ascending=False)
        .reset_index(drop=True)
    )


def tune_best_binary_model(X_train, y_train):
    """
    RandomizedSearchCV hyperparameter tuning for Random Forest on the
    binary job_ready task (25 iterations, 5-fold CV, ROC-AUC scoring).
    Returns: (best_estimator, best_params, best_cv_score)
    """
    param_distributions = {
        "n_estimators":      [200, 300, 400, 600],
        "max_depth":         [None, 4, 6, 8, 12],
        "min_samples_split": [2, 5, 10],
        "min_samples_leaf":  [1, 2, 4],
        "max_features":      ["sqrt", "log2", None],
    }
    base_model = RandomForestClassifier(random_state=RANDOM_STATE)
    cv         = StratifiedKFold(n_splits=5, shuffle=True, random_state=RANDOM_STATE)
    search     = RandomizedSearchCV(
        estimator=base_model,
        param_distributions=param_distributions,
        n_iter=25, scoring="roc_auc", cv=cv,
        random_state=RANDOM_STATE, n_jobs=-1, verbose=1,
    )
    search.fit(X_train, y_train)
    return search.best_estimator_, search.best_params_, search.best_score_


def train_binary_job_ready(X_train, X_test, y_train, y_test, preprocessor) -> dict:
    """
    Trains the binary job_ready classifier:
      1. Cross-validate all candidates  2. Tune Random Forest
      3. Evaluate on held-out test set  4. Save to models/job_ready_model.joblib
    """
    print("\n" + "=" * 70)
    print("BINARY CLASSIFICATION: job_ready")
    print("=" * 70)

    X_train_t, X_test_t = transform_features(preprocessor, X_train, X_test)

    scoring    = ["roc_auc", "f1", "accuracy", "precision", "recall"]
    cv_results = cross_validate_models(
        get_binary_candidate_models(), X_train_t, y_train, scoring
    )
    print("\nCross-validation results (sorted by ROC-AUC):")
    print(cv_results[
        ["model", "roc_auc_mean", "f1_mean", "accuracy_mean",
         "precision_mean", "recall_mean", "fit_time_sec"]
    ].to_string(index=False))

    print("\nTuning Random Forest (RandomizedSearchCV, 25 iter, 5-fold CV)...")
    best_model, best_params, best_cv_score = tune_best_binary_model(X_train_t, y_train)
    print(f"Best CV ROC-AUC: {best_cv_score:.4f}")
    print(f"Best parameters: {best_params}")

    best_model.fit(X_train_t, y_train)
    test_metrics = evaluate_binary_classifier(best_model, X_test_t, y_test)

    print("\nHeld-out TEST SET performance (Tuned Random Forest):")
    for k, v in test_metrics.items():
        if k != "confusion_matrix":
            print(f"  {k}: {v:.4f}" if isinstance(v, float) else f"  {k}: {v}")

    os.makedirs(MODELS_DIR, exist_ok=True)
    out_path = os.path.join(MODELS_DIR, "job_ready_model.joblib")
    joblib.dump(best_model, out_path)
    print(f"\nSaved -> {out_path}")

    return {
        "cv_results":       cv_results.to_dict(orient="records"),
        "best_params":      best_params,
        "best_cv_roc_auc":  best_cv_score,
        "test_metrics":     {k: v for k, v in test_metrics.items() if k != "confusion_matrix"},
        "confusion_matrix": test_metrics["confusion_matrix"],
    }


def train_multiclass_career_track(X_train, X_test, y_train, y_test, preprocessor) -> dict:
    """
    Trains the multi-class career_track classifier:
      1. Cross-validate all candidates  2. Select best by macro-F1
      3. Evaluate on held-out test set  4. Save to models/career_track_model.joblib
    """
    print("\n" + "=" * 70)
    print("MULTI-CLASS CLASSIFICATION: career_track")
    print("=" * 70)

    X_train_t, X_test_t = transform_features(preprocessor, X_train, X_test)

    scoring    = ["f1_macro", "accuracy"]
    cv_results = cross_validate_models(
        get_multiclass_candidate_models(), X_train_t, y_train, scoring
    )
    print("\nCross-validation results (sorted by Macro-F1):")
    print(cv_results[
        ["model", "f1_macro_mean", "accuracy_mean", "fit_time_sec"]
    ].to_string(index=False))

    best_model_name = cv_results.iloc[0]["model"]
    print(f"\nBest model by CV Macro-F1: {best_model_name}")

    best_model = get_multiclass_candidate_models()[best_model_name]
    best_model.fit(X_train_t, y_train)

    test_metrics = evaluate_multiclass_classifier(best_model, X_test_t, y_test)
    print(f"\nHeld-out TEST SET performance:")
    print(f"  Accuracy: {test_metrics['accuracy']:.4f}")
    print(f"  Macro-F1: {test_metrics['macro_f1']:.4f}")
    print("\nClassification Report:")
    print(test_metrics["classification_report"])

    os.makedirs(MODELS_DIR, exist_ok=True)
    out_path = os.path.join(MODELS_DIR, "career_track_model.joblib")
    joblib.dump(best_model, out_path)
    print(f"Saved -> {out_path}")

    return {
        "best_model_name": best_model_name,
        "algorithm":       best_model_name,
        "cv_results":      cv_results.to_dict(orient="records"),
        "test_accuracy":   test_metrics["accuracy"],
        "test_macro_f1":   test_metrics["macro_f1"],
        "confusion_matrix":test_metrics["confusion_matrix"],
    }


# ==============================================================================
# SECTION 7 — INFERENCE / PREDICTION  (predict.py)
# ==============================================================================

def load_artifacts():
    """Loads the fitted preprocessing pipeline and both trained models from disk."""
    pipeline    = joblib.load(os.path.join(MODELS_DIR, "preprocessing_pipeline.joblib"))
    job_model   = joblib.load(os.path.join(MODELS_DIR, "job_ready_model.joblib"))
    track_model = joblib.load(os.path.join(MODELS_DIR, "career_track_model.joblib"))
    return pipeline, job_model, track_model


def build_input_row(raw_input: dict) -> pd.DataFrame:
    """
    Converts a single student's raw input dict into a one-row DataFrame
    with every column the pipeline expects, filling omitted fields with
    neutral defaults so the app never crashes on a partial form.
    """
    defaults = {
        "age": 22, "cgpa": 7.0, "degree": "B.Tech",
        "python_score": 5.0, "sql_score": 5.0, "statistics_score": 5.0,
        "ml_score": 5.0, "dl_score": 5.0, "genai_score": 5.0,
        "ml_projects": 0, "end_to_end_projects": 0, "deployed_projects": 0,
        "kaggle_competitions": 0, "best_competition_rank": np.nan,
        "hackathons_attended": 0, "hackathons_won": 0, "finalist_status": 0,
        "github_projects": 0, "internship_months": 0, "certifications": 0,
        "ml_interview_score": 5.0, "communication_score": 5.0,
        "dsa_score": 5.0, "resume_score": 5.0, "mock_interview_score": 5.0,
    }
    row = {**defaults, **raw_input}
    df  = pd.DataFrame([row])
    df  = add_engineered_features(df)
    return get_feature_columns(df)


def predict_student(raw_input: dict, top_n: int = 5) -> dict:
    """
    Main inference entry point used by the Streamlit app.

    Args:
        raw_input: dict of student feature values (from the web form).
        top_n:     number of top features to include in the explanation.

    Returns:
        {
          "job_ready":                  0 or 1,
          "job_ready_probability":      float (0-1),
          "career_track":               int,
          "career_track_label":         str,
          "career_track_probabilities": {label: prob, ...},
          "top_positive_factors":       [(feature, value), ...],
          "areas_to_improve":           [feature, ...],
        }
    """
    pipeline, job_model, track_model = load_artifacts()

    X_row   = build_input_row(raw_input)
    X_row_t = pipeline.transform(X_row)

    job_ready_pred  = int(job_model.predict(X_row_t)[0])
    job_ready_proba = float(job_model.predict_proba(X_row_t)[0][1])

    track_pred      = int(track_model.predict(X_row_t)[0])
    track_proba_arr = track_model.predict_proba(X_row_t)[0]
    track_probs     = {
        CAREER_TRACK_LABELS[int(cls)]: float(p)
        for cls, p in zip(track_model.classes_, track_proba_arr)
    }

    feature_names = get_feature_names(pipeline)
    importance_df = built_in_feature_importance(job_model, feature_names)

    top_positive_factors: list = []
    areas_to_improve: list     = []

    if importance_df is not None:
        for feat in importance_df.head(top_n)["feature"].tolist():
            if feat in X_row.columns:
                value = X_row.iloc[0][feat]
                if isinstance(value, np.generic):
                    value = value.item()           # numpy scalar -> Python native
                top_positive_factors.append((feat, value))

        for feat, value in top_positive_factors:
            if isinstance(value, (int, float)) and 0 <= value <= 10 and value < 5:
                areas_to_improve.append(feat)

    return {
        "job_ready":                  job_ready_pred,
        "job_ready_probability":      job_ready_proba,
        "career_track":               track_pred,
        "career_track_label":         CAREER_TRACK_LABELS[track_pred],
        "career_track_probabilities": track_probs,
        "top_positive_factors":       top_positive_factors,
        "areas_to_improve":           areas_to_improve,
    }


# ==============================================================================
# MAIN — full end-to-end pipeline
# ==============================================================================

def main():
    """
    Runs the complete pipeline in order:
      [1] Generate raw dataset (skipped if already exists)
      [2] Preprocess data
      [3] Train binary classifier  (job_ready)
      [4] Train multi-class classifier (career_track)
      [5] Save model metadata JSON

    After running, launch the app:
        streamlit run app/streamlit_app.py
    """
    print("\n")
    print("=" * 70)
    print("HACKATHON SUCCESS & CAREER READINESS ML — FULL PIPELINE")
    print("=" * 70)

    # Step 1: generate raw data if missing
    raw_dir = os.path.join(PROJECT_ROOT, "data", "raw")
    os.makedirs(raw_dir, exist_ok=True)
    if not os.path.exists(RAW_DATA_PATH):
        print("\n[1/5] Generating synthetic dataset...")
        df = generate_dataset()
        df.to_csv(RAW_DATA_PATH, index=False)
        print(f"  Saved {len(df)} rows -> {RAW_DATA_PATH}")
        print("  job_ready distribution:")
        print(df["job_ready"].value_counts(normalize=True).rename("proportion").to_string())
    else:
        print(f"\n[1/5] Raw data already exists -> skipping generation.")

    # Step 2: preprocess
    print("\n[2/5] Preprocessing...")
    (X_train, X_test,
     y_job_train, y_job_test,
     y_track_train, y_track_test,
     preprocessor) = run_preprocessing()

    # Step 3: train binary model
    print("\n[3/5] Training binary classifier (job_ready)...")
    binary_results = train_binary_job_ready(
        X_train, X_test, y_job_train, y_job_test, preprocessor
    )

    # Step 4: train multi-class model
    print("\n[4/5] Training multi-class classifier (career_track)...")
    multiclass_results = train_multiclass_career_track(
        X_train, X_test, y_track_train, y_track_test, preprocessor
    )

    # Step 5: save metadata
    print("\n[5/5] Saving model metadata...")
    metadata = {
        "n_train":              len(X_train),
        "n_test":               len(X_test),
        "numeric_features":     NUMERIC_FEATURES,
        "categorical_features": CATEGORICAL_FEATURES,
        "job_ready_model": {
            "algorithm":   "RandomForestClassifier (Tuned)",
            "best_params": binary_results["best_params"],
            "cv_roc_auc":  binary_results["best_cv_roc_auc"],
            "test_metrics":binary_results["test_metrics"],
        },
        "career_track_model": {
            "algorithm":    multiclass_results["best_model_name"],
            "test_accuracy":multiclass_results["test_accuracy"],
            "test_macro_f1":multiclass_results["test_macro_f1"],
        },
    }
    metadata_path = os.path.join(MODELS_DIR, "model_metadata.json")
    os.makedirs(MODELS_DIR, exist_ok=True)
    with open(metadata_path, "w") as f:
        json.dump(metadata, f, indent=2, default=str)
    print(f"  Saved -> {metadata_path}")

    print("\n" + "=" * 70)
    print("PIPELINE COMPLETE!")
    print("=" * 70)
    print("Saved files:")
    print("  data/raw/student_employability.csv")
    print("  data/processed/processed_data.csv")
    print("  models/preprocessing_pipeline.joblib")
    print("  models/job_ready_model.joblib")
    print("  models/career_track_model.joblib")
    print("  models/model_metadata.json")
    print("=" * 70)
    print("\nNext step:  streamlit run app/streamlit_app.py")


if __name__ == "__main__":
    main()
