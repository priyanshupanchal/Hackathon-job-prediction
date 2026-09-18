"""
tests/test_preprocessing.py
 (Final Testing)

Run with:
    pytest tests/
or, if pytest is not installed:
    python tests/test_preprocessing.py
"""

import os
import sys

sys.path.append(os.path.join(os.path.dirname(__file__), "..", "src"))

import numpy as np
import pandas as pd

from features import add_engineered_features, ENGINEERED_FEATURE_NAMES
from preprocessing import (
    build_preprocessing_pipeline,
    clean_data,
    get_feature_columns,
    NUMERIC_FEATURES,
    CATEGORICAL_FEATURES,
)


def make_dummy_dataframe(n=20):
    rng = np.random.default_rng(0)
    hackathons_attended = rng.integers(0, 10, n)
    hackathons_won = np.minimum(rng.integers(0, 3, n), hackathons_attended)
    finalist_status = np.minimum(rng.integers(0, 3, n) + hackathons_won, hackathons_attended)
    return pd.DataFrame({
        "student_id": [f"S{i}" for i in range(n)],
        "age": rng.integers(20, 25, n),
        "degree": rng.choice(["B.Tech", "MCA"], n),
        "cgpa": rng.uniform(5, 10, n),
        "python_score": rng.uniform(0, 10, n),
        "sql_score": rng.uniform(0, 10, n),
        "statistics_score": rng.uniform(0, 10, n),
        "ml_score": rng.uniform(0, 10, n),
        "dl_score": rng.uniform(0, 10, n),
        "genai_score": rng.uniform(0, 10, n),
        "ml_projects": rng.integers(0, 5, n),
        "end_to_end_projects": rng.integers(0, 3, n),
        "deployed_projects": rng.integers(0, 2, n),
        "kaggle_competitions": rng.integers(0, 5, n),
        "best_competition_rank": [np.nan] * (n // 2) + list(rng.integers(1, 100, n - n // 2)),
        "hackathons_attended": hackathons_attended,
        "hackathons_won": hackathons_won,
        "finalist_status": finalist_status,
        "github_projects": rng.integers(0, 10, n),
        "internship_months": rng.integers(0, 6, n),
        "certifications": rng.integers(0, 5, n),
        "ml_interview_score": rng.uniform(0, 10, n),
        "communication_score": rng.uniform(0, 10, n),
        "dsa_score": rng.uniform(0, 10, n),
        "resume_score": rng.uniform(0, 10, n),
        "mock_interview_score": rng.uniform(0, 10, n),
        "job_ready": rng.integers(0, 2, n),
        "career_track": rng.integers(0, 6, n),
    })


def test_add_engineered_features_no_nans_in_derived_columns():
    df = make_dummy_dataframe()
    result = add_engineered_features(df)
    for col in ENGINEERED_FEATURE_NAMES:
        assert col in result.columns, f"missing engineered column {col}"
        if col != "competition_rank_missing":
            # rates/flags should never be NaN even with div-by-zero cases
            assert result[col].isna().sum() == 0, f"{col} contains NaNs"


def test_win_rate_bounds():
    df = make_dummy_dataframe()
    result = add_engineered_features(df)
    assert (result["hackathon_win_rate"] >= 0).all()
    assert (result["hackathon_win_rate"] <= 1).all()


def test_win_rate_zero_when_no_hackathons():
    df = make_dummy_dataframe(n=5)
    df["hackathons_attended"] = 0
    df["hackathons_won"] = 0
    result = add_engineered_features(df)
    assert (result["hackathon_win_rate"] == 0).all()


def test_clean_data_drops_duplicates():
    df = make_dummy_dataframe(n=5)
    df_with_dupe = pd.concat([df, df.iloc[[0]]], ignore_index=True)
    cleaned = clean_data(df_with_dupe)
    assert len(cleaned) == len(df)


def test_clean_data_clips_score_ranges():
    df = make_dummy_dataframe(n=5)
    df.loc[0, "python_score"] = 15  # invalid, should be clipped to 10
    df.loc[1, "sql_score"] = -3     # invalid, should be clipped to 0
    cleaned = clean_data(df)
    assert cleaned.loc[0, "python_score"] == 10
    assert cleaned.loc[1, "sql_score"] == 0


def test_pipeline_fits_and_transforms_without_error():
    df = make_dummy_dataframe(n=50)
    df = clean_data(df)
    df = add_engineered_features(df)
    X = get_feature_columns(df)

    pipeline = build_preprocessing_pipeline()
    pipeline.fit(X)
    X_transformed = pipeline.transform(X)

    assert X_transformed.shape[0] == len(X)
    assert not np.isnan(X_transformed).any(), "transformed output should have no NaNs"


def test_get_feature_columns_selects_expected_columns():
    df = make_dummy_dataframe(n=5)
    df = add_engineered_features(df)
    X = get_feature_columns(df)
    expected_cols = set(NUMERIC_FEATURES + CATEGORICAL_FEATURES)
    assert set(X.columns) == expected_cols


if __name__ == "__main__":
    # Allow running without pytest installed
    tests = [
        test_add_engineered_features_no_nans_in_derived_columns,
        test_win_rate_bounds,
        test_win_rate_zero_when_no_hackathons,
        test_clean_data_drops_duplicates,
        test_clean_data_clips_score_ranges,
        test_pipeline_fits_and_transforms_without_error,
        test_get_feature_columns_selects_expected_columns,
    ]
    passed = 0
    for t in tests:
        try:
            t()
            print(f"PASS: {t.__name__}")
            passed += 1
        except AssertionError as e:
            print(f"FAIL: {t.__name__} -> {e}")
    print(f"\n{passed}/{len(tests)} tests passed")
