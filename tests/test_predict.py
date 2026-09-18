"""
tests/test_predict.py
Owner: Dushant / Shilpi (Final Testing)

These tests require trained models to exist (run src/preprocessing.py and
src/train.py first). They verify the app's prediction path end-to-end.

Run with:
    pytest tests/
or:
    python tests/test_predict.py
"""

import os
import sys

sys.path.append(os.path.join(os.path.dirname(__file__), "..", "src"))

from predict import predict_student, CAREER_TRACK_LABELS

MODELS_DIR = os.path.join(os.path.dirname(__file__), "..", "models")

STRONG_CANDIDATE = {
    "age": 22, "cgpa": 8.5, "degree": "B.Tech",
    "python_score": 9, "sql_score": 8, "statistics_score": 8,
    "ml_score": 8, "dl_score": 7, "genai_score": 7,
    "ml_projects": 6, "end_to_end_projects": 4, "deployed_projects": 3,
    "kaggle_competitions": 3, "best_competition_rank": 25,
    "hackathons_attended": 2, "hackathons_won": 0, "finalist_status": 1,
    "github_projects": 10, "internship_months": 6, "certifications": 4,
    "ml_interview_score": 8, "communication_score": 8,
    "dsa_score": 7, "resume_score": 8, "mock_interview_score": 8,
}

HACKATHON_ONLY_CANDIDATE = {
    "age": 21, "cgpa": 6.0, "degree": "B.Tech",
    "python_score": 3, "sql_score": 3, "statistics_score": 2,
    "ml_score": 3, "dl_score": 2, "genai_score": 2,
    "ml_projects": 1, "end_to_end_projects": 0, "deployed_projects": 0,
    "kaggle_competitions": 0, "best_competition_rank": None,
    "hackathons_attended": 12, "hackathons_won": 2, "finalist_status": 4,
    "github_projects": 2, "internship_months": 0, "certifications": 0,
    "ml_interview_score": 3, "communication_score": 4,
    "dsa_score": 3, "resume_score": 3, "mock_interview_score": 3,
}


def _models_exist():
    return all(
        os.path.exists(os.path.join(MODELS_DIR, f))
        for f in [
            "preprocessing_pipeline.joblib",
            "job_ready_model.joblib",
            "career_track_model.joblib",
        ]
    )


def test_models_are_trained():
    assert _models_exist(), (
        "Trained model files not found. Run `python src/preprocessing.py` "
        "then `python src/train.py` first."
    )


def test_predict_returns_expected_keys():
    result = predict_student(STRONG_CANDIDATE)
    expected_keys = {
        "job_ready", "job_ready_probability", "career_track",
        "career_track_label", "career_track_probabilities",
        "top_positive_factors", "areas_to_improve",
    }
    assert expected_keys.issubset(result.keys())


def test_probability_is_valid_range():
    result = predict_student(STRONG_CANDIDATE)
    assert 0.0 <= result["job_ready_probability"] <= 1.0


def test_career_track_probabilities_sum_to_one():
    result = predict_student(STRONG_CANDIDATE)
    total = sum(result["career_track_probabilities"].values())
    assert abs(total - 1.0) < 1e-6


def test_career_track_label_is_valid():
    result = predict_student(STRONG_CANDIDATE)
    assert result["career_track_label"] in CAREER_TRACK_LABELS.values()


def test_strong_candidate_scores_higher_than_hackathon_only_candidate():
    """
    Sanity/regression test encoding the project's core hypothesis: a
    candidate strong in fundamentals + projects + internship should score
    HIGHER than one with many hackathons but weak fundamentals — even
    though the hackathon-only candidate attends far more hackathons.
    """
    strong_result = predict_student(STRONG_CANDIDATE)
    hackathon_result = predict_student(HACKATHON_ONLY_CANDIDATE)
    assert (
        strong_result["job_ready_probability"]
        > hackathon_result["job_ready_probability"]
    ), "Fundamentals+projects candidate should outscore hackathon-only candidate"


if __name__ == "__main__":
    tests = [
        test_models_are_trained,
        test_predict_returns_expected_keys,
        test_probability_is_valid_range,
        test_career_track_probabilities_sum_to_one,
        test_career_track_label_is_valid,
        test_strong_candidate_scores_higher_than_hackathon_only_candidate,
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
