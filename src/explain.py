"""
src/explain.py
(ML Engineer / Explainable AI)

Purpose:
    Model explainability utilities used by both the notebooks and the
    Streamlit app. Tries SHAP first (best per-prediction explanations);
    if SHAP isn't installed, falls back to scikit-learn's permutation
    importance so the project still runs with just requirements-lite.

    IMPORTANT: every number returned here is computed from the actual
    trained model on actual data. Nothing is hand-picked or invented —
    see the project rule "do not invent feature importance."
"""

import numpy as np
import pandas as pd
from sklearn.inspection import permutation_importance

try:
    import shap
    SHAP_AVAILABLE = True
except ImportError:
    SHAP_AVAILABLE = False


def get_feature_names(preprocessor) -> list:
    """
    Recovers human-readable feature names after ColumnTransformer
    (numeric names stay the same; categorical names become
    'degree_B.Tech', 'degree_MCA', etc.)
    """
    output_features = []
    for name, transformer, columns in preprocessor.transformers_:
        if name == "num":
            output_features.extend(columns)
        elif name == "cat":
            onehot = transformer.named_steps["onehot"]
            cat_names = onehot.get_feature_names_out(columns)
            output_features.extend(cat_names.tolist())
    return output_features


def global_feature_importance(model, X_test_transformed, y_test, feature_names,
                                n_repeats=10, random_state=42) -> pd.DataFrame:
    """
    Global feature importance via permutation importance (model-agnostic,
    always available). Returns a dataframe sorted by importance, highest
    first — this reflects how much test performance drops when a feature
    is shuffled, i.e. how much the model actually relies on it.
    """
    result = permutation_importance(
        model, X_test_transformed, y_test,
        n_repeats=n_repeats, random_state=random_state, n_jobs=-1,
    )
    df = pd.DataFrame({
        "feature": feature_names,
        "importance_mean": result.importances_mean,
        "importance_std": result.importances_std,
    }).sort_values("importance_mean", ascending=False).reset_index(drop=True)
    return df


def built_in_feature_importance(model, feature_names) -> pd.DataFrame:
    """
    Uses the model's own .feature_importances_ (tree-based models) or
    .coef_ (linear models) if available. Returns None if neither exists.
    """
    if hasattr(model, "feature_importances_"):
        importances = model.feature_importances_
    elif hasattr(model, "coef_"):
        importances = np.abs(model.coef_).mean(axis=0) if model.coef_.ndim > 1 else np.abs(model.coef_)
    else:
        return None

    df = pd.DataFrame({
        "feature": feature_names,
        "importance": importances,
    }).sort_values("importance", ascending=False).reset_index(drop=True)
    return df


def explain_single_prediction(model, preprocessor, X_row_transformed, feature_names,
                               background_data=None, top_n=5):
    """
    Explains ONE prediction (a single transformed row).

    If SHAP is available: uses TreeExplainer/Explainer for a real local
    explanation (SHAP values for this specific row).

    If SHAP is unavailable: falls back to the model's global feature
    importance (built-in or permutation) as an approximation, clearly
    labeled as such — it is NOT a true per-row explanation, just the
    best available substitute.

    Returns: (top_positive: list[(feature, value)], top_negative: list[(feature, value)], method: str)
    """
    if SHAP_AVAILABLE:
        try:
            explainer = shap.Explainer(model, background_data) if background_data is not None \
                else shap.Explainer(model)
            shap_values = explainer(X_row_transformed)
            values = shap_values.values[0]
            if values.ndim > 1:  # multi-class: use the predicted class's row
                pred_class = model.predict(X_row_transformed)[0]
                values = values[:, pred_class]

            pairs = list(zip(feature_names, values))
            pairs.sort(key=lambda x: x[1], reverse=True)
            top_positive = [p for p in pairs if p[1] > 0][:top_n]
            top_negative = [p for p in pairs if p[1] < 0][-top_n:]
            return top_positive, top_negative, "shap"
        except Exception:
            pass  # fall through to the fallback below

    # Fallback: built-in feature importance (not row-specific, but honest
    # about that limitation — see the app UI copy in streamlit_app.py)
    importance_df = built_in_feature_importance(model, feature_names)
    if importance_df is None:
        return [], [], "unavailable"

    top_features = importance_df.head(top_n)
    top_positive = list(zip(top_features["feature"], top_features["importance"]))
    return top_positive, [], "global_importance_fallback"
