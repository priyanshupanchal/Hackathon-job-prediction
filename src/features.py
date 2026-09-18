"""
src/features.py
(Data Engineer)

Purpose:
    Feature engineering functions used by the preprocessing pipeline.
    These are applied BEFORE the ColumnTransformer (imputation/scaling/
    encoding) so that engineered ratios are computed from raw counts,
    not from already-scaled values.

    All functions are pure (take a DataFrame, return a new DataFrame) so
    they are easy to unit test and easy to reuse identically in training
    and in the Streamlit app (no train/serve skew).
"""

import numpy as np
import pandas as pd


def add_engineered_features(df: pd.DataFrame) -> pd.DataFrame:
    """
    Adds derived features that are more informative than the raw counts
    alone. Returns a NEW dataframe (does not mutate the input in place).
    """
    df = df.copy()

    # --- Hackathon efficiency signals ---
    # Win rate: winning relative to attendance (guards against div-by-zero)
    df["hackathon_win_rate"] = np.where(
        df["hackathons_attended"] > 0,
        df["hackathons_won"] / df["hackathons_attended"],
        0.0,
    )
    df["hackathon_finalist_rate"] = np.where(
        df["hackathons_attended"] > 0,
        df["finalist_status"] / df["hackathons_attended"],
        0.0,
    )
    # Binary flag: has this student won at least one hackathon?
    df["has_won_hackathon"] = (df["hackathons_won"] > 0).astype(int)

    # --- Project depth signals ---
    df["deployment_rate"] = np.where(
        df["end_to_end_projects"] > 0,
        df["deployed_projects"] / df["end_to_end_projects"],
        0.0,
    )
    df["end_to_end_rate"] = np.where(
        df["ml_projects"] > 0,
        df["end_to_end_projects"] / df["ml_projects"],
        0.0,
    )
    df["has_deployed_project"] = (df["deployed_projects"] > 0).astype(int)

    # --- Competition exposure ---
    df["has_competed_kaggle"] = (df["kaggle_competitions"] > 0).astype(int)
    # Missing best_competition_rank means "never competed" -> structural,
    # not random. Encode explicitly instead of silently imputing later.
    df["competition_rank_missing"] = df["best_competition_rank"].isna().astype(int)

    # --- Aggregate skill index (simple average of the 6 core skills) ---
    skill_cols = [
        "python_score", "sql_score", "statistics_score",
        "ml_score", "dl_score", "genai_score",
    ]
    df["avg_core_skill_score"] = df[skill_cols].mean(axis=1)

    # --- Real-world exposure index ---
    df["real_world_exposure"] = (
        df["internship_months"] + df["deployed_projects"] * 2 + df["end_to_end_projects"]
    )

    # --- Interview readiness index ---
    interview_cols = ["ml_interview_score", "communication_score",
                       "dsa_score", "resume_score", "mock_interview_score"]
    df["avg_interview_readiness"] = df[interview_cols].mean(axis=1)

    return df


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
