"""
src/evaluate.py
 (ML Engineer)

Purpose:
    Shared evaluation functions for binary (job_ready) and multi-class
    (career_track) classifiers. Kept separate from train.py so the exact
    same evaluation logic can be reused in notebooks, train.py, and tests.
"""

import numpy as np
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


def evaluate_binary_classifier(model, X_test, y_test) -> dict:
    """
    Returns a dict of accuracy, precision, recall, f1, roc_auc, pr_auc and
    the confusion matrix for a fitted binary classifier.
    """
    y_pred = model.predict(X_test)

    if hasattr(model, "predict_proba"):
        y_proba = model.predict_proba(X_test)[:, 1]
    else:
        # Some models (e.g. SVM without probability=True) only give scores
        y_proba = model.decision_function(X_test)

    metrics = {
        "accuracy": accuracy_score(y_test, y_pred),
        "precision": precision_score(y_test, y_pred, zero_division=0),
        "recall": recall_score(y_test, y_pred, zero_division=0),
        "f1": f1_score(y_test, y_pred, zero_division=0),
        "roc_auc": roc_auc_score(y_test, y_proba),
        "pr_auc": average_precision_score(y_test, y_proba),
        "confusion_matrix": confusion_matrix(y_test, y_pred).tolist(),
    }
    return metrics


def evaluate_multiclass_classifier(model, X_test, y_test) -> dict:
    """
    Returns accuracy, macro-F1, a full classification report (as text)
    and the confusion matrix for a fitted multi-class classifier.
    """
    y_pred = model.predict(X_test)

    metrics = {
        "accuracy": accuracy_score(y_test, y_pred),
        "macro_f1": f1_score(y_test, y_pred, average="macro", zero_division=0),
        "classification_report": classification_report(
            y_test, y_pred, zero_division=0
        ),
        "confusion_matrix": confusion_matrix(y_test, y_pred).tolist(),
    }
    return metrics


def error_analysis(model, X_test, y_test, original_df, top_n=10):
    """
    Returns the `top_n` most confidently WRONG predictions (largest gap
    between predicted probability and the true label) so the team can
    inspect what the model gets wrong and why, instead of only looking
    at aggregate metrics.

    `original_df` must be the (untransformed) test-set dataframe with the
    same row order as X_test/y_test, so we can show human-readable inputs
    alongside the prediction error.
    """
    y_pred = model.predict(X_test)
    if hasattr(model, "predict_proba"):
        y_proba = model.predict_proba(X_test)
        confidence = y_proba.max(axis=1)
    else:
        confidence = np.ones(len(y_pred))

    errors = original_df.copy().reset_index(drop=True)
    errors["true_label"] = np.array(y_test).reshape(-1)
    errors["predicted_label"] = y_pred
    errors["confidence"] = confidence
    errors = errors[errors["true_label"] != errors["predicted_label"]]
    errors = errors.sort_values("confidence", ascending=False).head(top_n)
    return errors
