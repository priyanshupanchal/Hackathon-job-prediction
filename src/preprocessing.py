"""
src/preprocessing.py
(Data Engineer)

Purpose:
    - Load the raw dataset
    - Apply feature engineering (src/features.py)
    - Build a scikit-learn Pipeline/ColumnTransformer that handles
      imputation, scaling and one-hot encoding
    - Split into train/test BEFORE fitting the pipeline (prevents data
      leakage — the pipeline is fit only on training data, then reused
      to transform the test set and, later, live app inputs)
    - Save the processed data and the fitted pipeline for downstream use

Run directly:
    python src/preprocessing.py
"""

import os
import joblib
import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.impute import SimpleImputer
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import StandardScaler, OneHotEncoder
from sklearn.model_selection import train_test_split

from features import add_engineered_features, ENGINEERED_FEATURE_NAMES

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))

RAW_DATA_PATH = os.path.join(PROJECT_ROOT, "data", "raw", "student_employability.csv")
PROCESSED_DATA_DIR = os.path.join(PROJECT_ROOT, "data", "processed")
PIPELINE_PATH = os.path.join(PROJECT_ROOT, "models", "preprocessing_pipeline.joblib")

# Columns dropped before modeling (identifiers / raw targets handled separately)
ID_COLUMNS = ["student_id"]
TARGET_COLUMNS = ["job_ready", "career_track"]

CATEGORICAL_FEATURES = ["degree"]

# Numeric features = every raw numeric column except targets/ids, PLUS the
# engineered ones added by add_engineered_features(). best_competition_rank
# is included; its NaNs are handled by the imputer, and
# `competition_rank_missing` (engineered) preserves the missingness signal.
BASE_NUMERIC_FEATURES = [
    "age", "cgpa", "python_score", "sql_score", "statistics_score",
    "ml_score", "dl_score", "genai_score", "ml_projects",
    "end_to_end_projects", "deployed_projects", "kaggle_competitions",
    "best_competition_rank", "hackathons_attended", "hackathons_won",
    "finalist_status", "github_projects", "internship_months",
    "certifications", "ml_interview_score", "communication_score",
    "dsa_score", "resume_score", "mock_interview_score",
]

NUMERIC_FEATURES = BASE_NUMERIC_FEATURES + ENGINEERED_FEATURE_NAMES


def load_raw_data(path: str = RAW_DATA_PATH) -> pd.DataFrame:
    df = pd.read_csv(path)
    return df


def clean_data(df: pd.DataFrame) -> pd.DataFrame:
    """
    Basic cleaning: drop exact duplicates, enforce sane ranges. Missing
    values are NOT dropped here — they are handled by the pipeline's
    imputers so the same logic applies at inference time in the app.
    """
    df = df.copy()
    before = len(df)
    df = df.drop_duplicates(subset=[c for c in df.columns if c != "student_id"])
    after = len(df)
    if before != after:
        print(f"Dropped {before - after} duplicate rows")

    # Clip obviously invalid values defensively (won't trigger on our
    # generator's output, but protects against bad real-world input later)
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
    """
    numeric_transformer = Pipeline(steps=[
        ("imputer", SimpleImputer(strategy="median")),
        ("scaler", StandardScaler()),
    ])

    categorical_transformer = Pipeline(steps=[
        ("imputer", SimpleImputer(strategy="most_frequent")),
        ("onehot", OneHotEncoder(handle_unknown="ignore")),
    ])

    preprocessor = ColumnTransformer(transformers=[
        ("num", numeric_transformer, NUMERIC_FEATURES),
        ("cat", categorical_transformer, CATEGORICAL_FEATURES),
    ])

    return preprocessor


def get_feature_columns(df: pd.DataFrame) -> pd.DataFrame:
    """Selects only the model input columns, in a fixed order."""
    return df[NUMERIC_FEATURES + CATEGORICAL_FEATURES]


def run_preprocessing(test_size: float = 0.2, random_state: int = 42):
    """
    End-to-end preprocessing run:
      1. Load raw data
      2. Clean
      3. Feature engineering
      4. Train/test split (stratified on job_ready)
      5. Fit ColumnTransformer on TRAIN ONLY
      6. Transform train & test
      7. Save processed CSVs (pre-transform, human-readable) and the
         fitted pipeline (for the app / training scripts to reuse)
    """
    os.makedirs(PROCESSED_DATA_DIR, exist_ok=True)
    os.makedirs("models", exist_ok=True)

    df = load_raw_data()
    df = clean_data(df)
    df = add_engineered_features(df)

    X = get_feature_columns(df)
    y_job_ready = df["job_ready"]
    y_career_track = df["career_track"]

    # Single split shared by both targets keeps the same rows together,
    # which matters because career_track depends on job_ready.
    (X_train, X_test,
     y_job_train, y_job_test,
     y_track_train, y_track_test) = train_test_split(
        X, y_job_ready, y_career_track,
        test_size=test_size,
        random_state=random_state,
        stratify=y_job_ready,
    )

    preprocessor = build_preprocessing_pipeline()
    preprocessor.fit(X_train)  # FIT ON TRAIN ONLY -> prevents leakage

    joblib.dump(preprocessor, PIPELINE_PATH)
    print(f"Saved fitted preprocessing pipeline to {PIPELINE_PATH}")

    # Save human-readable (pre-transform) processed splits, with targets,
    # so training scripts and notebooks can load them directly.
    train_df = X_train.copy()
    train_df["job_ready"] = y_job_train.values
    train_df["career_track"] = y_track_train.values
    train_df["split"] = "train"

    test_df = X_test.copy()
    test_df["job_ready"] = y_job_test.values
    test_df["career_track"] = y_track_test.values
    test_df["split"] = "test"

    processed_df = pd.concat([train_df, test_df], axis=0).reset_index(drop=True)
    processed_path = os.path.join(PROCESSED_DATA_DIR, "processed_data.csv")
    processed_df.to_csv(processed_path, index=False)
    print(f"Saved processed data ({processed_df.shape}) to {processed_path}")

    return X_train, X_test, y_job_train, y_job_test, y_track_train, y_track_test, preprocessor


if __name__ == "__main__":
    run_preprocessing()
