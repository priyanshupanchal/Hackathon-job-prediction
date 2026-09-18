"""
src/train.py
(ML Engineer)

Purpose:
    Train and compare multiple classification models for BOTH targets:
      1. job_ready       (binary classification)
      2. career_track    (multi-class classification, evaluated only on
                          job_ready == 1 rows plus class 5 "Not Yet Ready")

    Models trained:
      - Logistic Regression   (interpretable baseline)
      - Decision Tree         (simple rule-based baseline)
      - Random Forest         (nonlinear ensemble)
      

    For each target:
      - Stratified 5-fold cross-validation on the training set
      - Hyperparameter tuning via RandomizedSearchCV on the strongest
        candidate model
      - Final evaluation on the held-out test set (see src/evaluate.py)
      - Best model + preprocessing pipeline + metadata saved to models/

Run directly:
    python src/train.py
"""

import json
import os
import time

import joblib
import numpy as np
import pandas as pd

from sklearn.ensemble import RandomForestClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.model_selection import (
    RandomizedSearchCV,
    StratifiedKFold,
    cross_validate,
)
from sklearn.tree import DecisionTreeClassifier

from preprocessing import (
    NUMERIC_FEATURES,
    CATEGORICAL_FEATURES,
    run_preprocessing,
)

from evaluate import (
    evaluate_binary_classifier,
    evaluate_multiclass_classifier,
)


# ============================================================
# PATHS & SETTINGS
# ============================================================

MODELS_DIR = os.path.join(
    os.path.abspath(
        os.path.join(os.path.dirname(__file__), "..")
    ),
    "models"
)

RANDOM_STATE = 42


# ============================================================
# BINARY CLASSIFICATION MODELS
# Target: job_ready
# 0 = Not Job Ready
# 1 = Job Ready
# ============================================================

def get_binary_candidate_models():

    return {

        "LogisticRegression": LogisticRegression(
            max_iter=1000,
            random_state=RANDOM_STATE
        ),

        "DecisionTree": DecisionTreeClassifier(
            max_depth=6,
            random_state=RANDOM_STATE
        ),

        "RandomForest": RandomForestClassifier(
            n_estimators=300,
            random_state=RANDOM_STATE
        ),
    }


# ============================================================
# MULTI-CLASS CLASSIFICATION MODELS
# Target: career_track
# ============================================================

def get_multiclass_candidate_models():

    return {

        "LogisticRegression": LogisticRegression(
            max_iter=1000,
            random_state=RANDOM_STATE
        ),

        "DecisionTree": DecisionTreeClassifier(
            max_depth=6,
            random_state=RANDOM_STATE
        ),

        "RandomForest": RandomForestClassifier(
            n_estimators=300,
            random_state=RANDOM_STATE
        ),
    }


# ============================================================
# FEATURE TRANSFORMATION
# ============================================================

def transform_features(
    preprocessor,
    X_train,
    X_test
):

    """
    Applies the already-fitted preprocessing
    pipeline to train and test data.
    """

    X_train_t = preprocessor.transform(X_train)

    X_test_t = preprocessor.transform(X_test)

    return X_train_t, X_test_t


# ============================================================
# CROSS VALIDATION
# ============================================================

def cross_validate_models(
    models,
    X,
    y,
    scoring,
    cv_folds=5
):

    """
    Runs Stratified K-Fold Cross Validation
    for all candidate models.
    """

    cv = StratifiedKFold(
        n_splits=cv_folds,
        shuffle=True,
        random_state=RANDOM_STATE
    )

    rows = []

    for name, model in models.items():

        print(f"\nTraining: {name}")

        start = time.time()

        scores = cross_validate(
            model,
            X,
            y,
            cv=cv,
            scoring=scoring,
            n_jobs=-1,
            error_score="raise"
        )

        elapsed = time.time() - start

        row = {
            "model": name,
            "fit_time_sec": round(elapsed, 2)
        }

        for metric in scoring:

            key = f"test_{metric}"

            row[f"{metric}_mean"] = np.mean(
                scores[key]
            )

            row[f"{metric}_std"] = np.std(
                scores[key]
            )

        rows.append(row)

    results = pd.DataFrame(rows)

    primary_metric = f"{scoring[0]}_mean"

    results = results.sort_values(
        primary_metric,
        ascending=False
    ).reset_index(drop=True)

    return results


# ============================================================
# RANDOM FOREST HYPERPARAMETER TUNING
# ============================================================

def tune_best_binary_model(
    X_train,
    y_train
):

    """
    Hyperparameter tuning for Random Forest.

    XGBoost / LightGBM are NOT used.
    """

    param_distributions = {

        "n_estimators": [
            200,
            300,
            400,
            600
        ],

        "max_depth": [
            None,
            4,
            6,
            8,
            12
        ],

        "min_samples_split": [
            2,
            5,
            10
        ],

        "min_samples_leaf": [
            1,
            2,
            4
        ],

        "max_features": [
            "sqrt",
            "log2",
            None
        ],
    }

    base_model = RandomForestClassifier(
        random_state=RANDOM_STATE
    )

    cv = StratifiedKFold(
        n_splits=5,
        shuffle=True,
        random_state=RANDOM_STATE
    )

    search = RandomizedSearchCV(

        estimator=base_model,

        param_distributions=param_distributions,

        n_iter=25,

        scoring="roc_auc",

        cv=cv,

        random_state=RANDOM_STATE,

        n_jobs=-1,

        verbose=1
    )

    search.fit(
        X_train,
        y_train
    )

    return (
        search.best_estimator_,
        search.best_params_,
        search.best_score_
    )


# ============================================================
# TRAIN JOB READINESS MODEL
# ============================================================

def train_binary_job_ready(
    X_train,
    X_test,
    y_train,
    y_test,
    preprocessor
):

    print("\n" + "=" * 70)

    print(
        "BINARY CLASSIFICATION: job_ready"
    )

    print("=" * 70)


    # --------------------------------------------------------
    # Transform data
    # --------------------------------------------------------

    X_train_t, X_test_t = transform_features(
        preprocessor,
        X_train,
        X_test
    )


    # --------------------------------------------------------
    # Candidate models
    # --------------------------------------------------------

    scoring = [
        "roc_auc",
        "f1",
        "accuracy",
        "precision",
        "recall"
    ]

    models = get_binary_candidate_models()


    # --------------------------------------------------------
    # Cross Validation
    # --------------------------------------------------------

    cv_results = cross_validate_models(
        models,
        X_train_t,
        y_train,
        scoring
    )


    print(
        "\nCross-validation results "
        "(sorted by ROC-AUC):"
    )

    print(
        cv_results[
            [
                "model",
                "roc_auc_mean",
                "f1_mean",
                "accuracy_mean",
                "precision_mean",
                "recall_mean",
                "fit_time_sec"
            ]
        ].to_string(index=False)
    )


    # --------------------------------------------------------
    # Tune Random Forest
    # --------------------------------------------------------

    print(
        "\n"
        "Tuning Random Forest "
        "(RandomizedSearchCV, 25 iterations, 5-fold CV)..."
    )


    best_model, best_params, best_cv_score = (
        tune_best_binary_model(
            X_train_t,
            y_train
        )
    )


    print(
        f"\nBest CV ROC-AUC: "
        f"{best_cv_score:.4f}"
    )

    print(
        f"Best parameters: "
        f"{best_params}"
    )


    # --------------------------------------------------------
    # Train final model
    # --------------------------------------------------------

    best_model.fit(
        X_train_t,
        y_train
    )


    # --------------------------------------------------------
    # Test Evaluation
    # --------------------------------------------------------

    test_metrics = evaluate_binary_classifier(
        best_model,
        X_test_t,
        y_test
    )


    print(
        "\nHeld-out TEST SET performance "
        "(Tuned Random Forest):"
    )


    for key, value in test_metrics.items():

        if key != "confusion_matrix":

            if isinstance(value, float):

                print(
                    f"  {key}: {value:.4f}"
                )

            else:

                print(
                    f"  {key}: {value}"
                )


    # --------------------------------------------------------
    # Save model
    # --------------------------------------------------------

    os.makedirs(
        MODELS_DIR,
        exist_ok=True
    )


    joblib.dump(
        best_model,
        os.path.join(
            MODELS_DIR,
            "job_ready_model.joblib"
        )
    )


    print(
        "\nSaved:"
        "\nmodels/job_ready_model.joblib"
    )


    return {

        "cv_results":
            cv_results.to_dict(
                orient="records"
            ),

        "best_params":
            best_params,

        "best_cv_roc_auc":
            best_cv_score,

        "test_metrics": {
            key: value
            for key, value in test_metrics.items()
            if key != "confusion_matrix"
        },

        "confusion_matrix":
            test_metrics["confusion_matrix"],
    }


# ============================================================
# TRAIN CAREER TRACK MODEL
# ============================================================

def train_multiclass_career_track(
    X_train,
    X_test,
    y_train,
    y_test,
    preprocessor
):

    print("\n" + "=" * 70)

    print(
        "MULTI-CLASS CLASSIFICATION: career_track"
    )

    print("=" * 70)


    # --------------------------------------------------------
    # Transform features
    # --------------------------------------------------------

    X_train_t, X_test_t = transform_features(
        preprocessor,
        X_train,
        X_test
    )


    # --------------------------------------------------------
    # Models
    # --------------------------------------------------------

    scoring = [
        "f1_macro",
        "accuracy"
    ]

    models = get_multiclass_candidate_models()


    # --------------------------------------------------------
    # Cross Validation
    # --------------------------------------------------------

    cv_results = cross_validate_models(
        models,
        X_train_t,
        y_train,
        scoring
    )


    print(
        "\nCross-validation results "
        "(sorted by Macro-F1):"
    )


    print(
        cv_results[
            [
                "model",
                "f1_macro_mean",
                "accuracy_mean",
                "fit_time_sec"
            ]
        ].to_string(index=False)
    )


    # --------------------------------------------------------
    # Select best model
    # --------------------------------------------------------

    best_model_name = cv_results.iloc[0]["model"]


    print(
        f"\nBest model by CV Macro-F1: "
        f"{best_model_name}"
    )


    best_model = models[
        best_model_name
    ]


    # --------------------------------------------------------
    # Train
    # --------------------------------------------------------

    best_model.fit(
        X_train_t,
        y_train
    )


    # --------------------------------------------------------
    # Evaluate
    # --------------------------------------------------------

    test_metrics = evaluate_multiclass_classifier(
        best_model,
        X_test_t,
        y_test
    )


    print(
        "\nHeld-out TEST SET performance:"
    )

    print(
        f"  Accuracy: "
        f"{test_metrics['accuracy']:.4f}"
    )

    print(
        f"  Macro-F1: "
        f"{test_metrics['macro_f1']:.4f}"
    )


    print(
        "\nClassification Report:"
    )

    print(
        test_metrics[
            "classification_report"
        ]
    )


    # --------------------------------------------------------
    # Save model
    # --------------------------------------------------------

    os.makedirs(
        MODELS_DIR,
        exist_ok=True
    )


    joblib.dump(
        best_model,
        os.path.join(
            MODELS_DIR,
            "career_track_model.joblib"
        )
    )


    print(
        "\nSaved:"
        "\nmodels/career_track_model.joblib"
    )


    return {

        "best_model_name":
            best_model_name,

        "cv_results":
            cv_results.to_dict(
                orient="records"
            ),

        "test_accuracy":
            test_metrics["accuracy"],

        "test_macro_f1":
            test_metrics["macro_f1"],

        "confusion_matrix":
            test_metrics["confusion_matrix"],
    }


# ============================================================
# MAIN
# ============================================================

def main():

    print("\n")
    print("=" * 70)
    print(
        "HACKATHON SUCCESS & CAREER READINESS ML"
    )
    print("=" * 70)


    # --------------------------------------------------------
    # Preprocessing
    # --------------------------------------------------------

    (
        X_train,
        X_test,
        y_job_train,
        y_job_test,
        y_track_train,
        y_track_test,
        preprocessor
    ) = run_preprocessing()


    # --------------------------------------------------------
    # Binary Model
    # --------------------------------------------------------

    binary_results = train_binary_job_ready(

        X_train,
        X_test,

        y_job_train,
        y_job_test,

        preprocessor
    )


    # --------------------------------------------------------
    # Multi-class Model
    # --------------------------------------------------------

    multiclass_results = (
        train_multiclass_career_track(

            X_train,
            X_test,

            y_track_train,
            y_track_test,

            preprocessor
        )
    )


    # --------------------------------------------------------
    # Metadata
    # --------------------------------------------------------

    metadata = {

        "n_train":
            len(X_train),

        "n_test":
            len(X_test),

        "numeric_features":
            NUMERIC_FEATURES,

        "categorical_features":
            CATEGORICAL_FEATURES,


        "job_ready_model": {

            "algorithm":
                "RandomForestClassifier (Tuned)",

            "best_params":
                binary_results[
                    "best_params"
                ],

            "cv_roc_auc":
                binary_results[
                    "best_cv_roc_auc"
                ],

            "test_metrics":
                binary_results[
                    "test_metrics"
                ],
        },


        "career_track_model": {

            "algorithm":
                multiclass_results[
                    "best_model_name"
                ],

            "test_accuracy":
                multiclass_results[
                    "test_accuracy"
                ],

            "test_macro_f1":
                multiclass_results[
                    "test_macro_f1"
                ],
        },
    }


    # --------------------------------------------------------
    # Save metadata
    # --------------------------------------------------------

    os.makedirs(
        MODELS_DIR,
        exist_ok=True
    )


    with open(
        os.path.join(
            MODELS_DIR,
            "model_metadata.json"
        ),
        "w"
    ) as f:

        json.dump(
            metadata,
            f,
            indent=2,
            default=str
        )


    # --------------------------------------------------------
    # Final Message
    # --------------------------------------------------------

    print("\n" + "=" * 70)

    print(
        "TRAINING COMPLETE!"
    )

    print("=" * 70)

    print(
        "\nSaved files:"
    )

    print(
        "  ✓ job_ready_model.joblib"
    )

    print(
        "  ✓ career_track_model.joblib"
    )

    print(
        "  ✓ model_metadata.json"
    )

    print(
        "  ✓ preprocessing_pipeline.joblib"
    )

    print("=" * 70)


# ============================================================
# RUN
# ============================================================

if __name__ == "__main__":
    main()
