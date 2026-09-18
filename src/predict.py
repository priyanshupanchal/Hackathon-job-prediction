"""
src/predict.py
 (ML Engineer)

Purpose:
    Loads the saved preprocessing pipeline + trained models and exposes
    ONE function, `predict_student()`, that the Streamlit app calls with
    a single student's raw inputs (a dict) and gets back:
      - job readiness prediction + probability
      - recommended career track + class probabilities
      - top positive factors / areas to improve (explainability)

    Using the SAME add_engineered_features() + preprocessing pipeline
    here as in training guarantees there is no train/serve skew.

    run 
    python src/predict.py
"""

import os
import joblib
import numpy as np
import pandas as pd

from features import add_engineered_features
from preprocessing import get_feature_columns, NUMERIC_FEATURES, CATEGORICAL_FEATURES
from explain import get_feature_names, built_in_feature_importance

MODELS_DIR = os.path.join(os.path.dirname(__file__), "..", "models")

CAREER_TRACK_LABELS = {
    0: "Data Analyst",
    1: "Data Scientist",
    2: "ML Engineer",
    3: "Deep Learning Engineer",
    4: "GenAI Engineer",
    5: "Not Yet Ready",
}


def load_artifacts():
    """Loads the fitted pipeline + both trained models from disk."""
    pipeline = joblib.load(os.path.join(MODELS_DIR, "preprocessing_pipeline.joblib"))
    job_model = joblib.load(os.path.join(MODELS_DIR, "job_ready_model.joblib"))
    track_model = joblib.load(os.path.join(MODELS_DIR, "career_track_model.joblib"))
    return pipeline, job_model, track_model


def build_input_row(raw_input: dict) -> pd.DataFrame:
    """
    Converts a single student's raw input dict into a one-row dataframe
    with every column the pipeline expects, filling any field the caller
    omitted with a neutral default so the app never crashes on a partial
    form (though the Streamlit UI should always send everything).
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
    df = pd.DataFrame([row])
    df = add_engineered_features(df)
    return get_feature_columns(df)


def predict_student(raw_input: dict, top_n: int = 5) -> dict:
    """
    Main entry point used by the Streamlit app.

    Returns a dict:
        {
          "job_ready": 0 or 1,
          "job_ready_probability": float (0-1),
          "career_track": int,
          "career_track_label": str,
          "career_track_probabilities": {label: prob, ...},
          "top_positive_factors": [(feature, importance), ...],
          "explanation_method": "shap" | "global_importance_fallback" | ...
        }
    """
    pipeline, job_model, track_model = load_artifacts()

    X_row = build_input_row(raw_input)
    X_row_t = pipeline.transform(X_row)

    job_ready_pred = int(job_model.predict(X_row_t)[0])
    job_ready_proba = float(job_model.predict_proba(X_row_t)[0][1])

    track_pred = int(track_model.predict(X_row_t)[0])
    track_proba_arr = track_model.predict_proba(X_row_t)[0]
    track_classes = track_model.classes_
    track_probabilities = {
        CAREER_TRACK_LABELS[int(cls)]: float(p)
        for cls, p in zip(track_classes, track_proba_arr)
    }

    feature_names = get_feature_names(pipeline)
    importance_df = built_in_feature_importance(job_model, feature_names)
    top_positive_factors = []
    areas_to_improve = []
    if importance_df is not None:
        top_features = importance_df.head(top_n)["feature"].tolist()
        # For each top-driving feature, show the student's own value so the
        # app can say "you are strong/weak in X" rather than just naming X.
        for feat in top_features:
            if feat in X_row.columns:
                value = X_row.iloc[0][feat]
                if isinstance(value, np.generic):
                    value = value.item()  # numpy int64/float64 -> native Python type
                top_positive_factors.append((feat, value))

        # crude "areas to improve": among top features, flag ones that are
        # numeric score-like and below 5/10 for this student
        for feat, value in top_positive_factors:
            if isinstance(value, (int, float)) and 0 <= value <= 10 and value < 5:
                areas_to_improve.append(feat)

    return {
        "job_ready": job_ready_pred,
        "job_ready_probability": job_ready_proba,
        "career_track": track_pred,
        "career_track_label": CAREER_TRACK_LABELS[track_pred],
        "career_track_probabilities": track_probabilities,
        "top_positive_factors": top_positive_factors,
        "areas_to_improve": areas_to_improve,
    }


if __name__ == "__main__":
    # Quick manual smoke test
    sample_student = {
        "age": 22, "cgpa": 8.2, "degree": "B.Tech",
        "python_score": 8, "sql_score": 7, "statistics_score": 6,
        "ml_score": 7, "dl_score": 5, "genai_score": 7,
        "ml_projects": 4, "end_to_end_projects": 2, "deployed_projects": 1,
        "kaggle_competitions": 5, "best_competition_rank": 18,
        "hackathons_attended": 7, "hackathons_won": 1, "finalist_status": 2,
        "github_projects": 8, "internship_months": 3, "certifications": 5,
        "ml_interview_score": 6, "communication_score": 8,
        "dsa_score": 6, "resume_score": 7, "mock_interview_score": 7,
    }
    result = predict_student(sample_student)
    import json
    print(json.dumps(result, indent=2, default=str))
