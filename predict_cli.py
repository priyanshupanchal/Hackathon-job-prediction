"""
predict_cli.py
--------------
Standalone CLI bridge between PHP and the ML models.
PHP calls this script directly via proc_open() or exec() — NO Flask server needed.

Usage (called by predict.php via proc_open):
    echo '<json>' | python predict_cli.py

Reads JSON payload from stdin, outputs single-line JSON to stdout.
Errors: JSON {"error": "..."} to stdout (never raises uncaught exception).
"""

import sys
import os
import json

# Make src/ importable regardless of CWD
_BASE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(_BASE, "src"))


def main():
    # Read JSON from stdin (safe for all shells, handles unicode, large payloads)
    try:
        raw_json = sys.stdin.read().strip()
        if not raw_json:
            print(json.dumps({"error": "Empty input received by predict_cli.py"}))
            sys.exit(1)
        data = json.loads(raw_json)
    except json.JSONDecodeError as e:
        print(json.dumps({"error": f"Invalid JSON input: {e}"}))
        sys.exit(1)
    except Exception as e:
        print(json.dumps({"error": f"Input error: {e}"}))
        sys.exit(1)

    # ── Parse fields (same logic as predict_api.py) ────────────────────────
    numeric_int_fields = [
        "age", "ml_projects", "end_to_end_projects", "deployed_projects",
        "kaggle_competitions", "best_competition_rank", "hackathons_attended",
        "hackathons_won", "finalist_status", "github_projects",
        "internship_months", "certifications",
    ]
    numeric_float_fields = [
        "cgpa", "python_score", "sql_score", "statistics_score",
        "ml_score", "dl_score", "genai_score",
        "ml_interview_score", "communication_score", "dsa_score",
        "resume_score", "mock_interview_score",
    ]

    student_input = {}
    for field in numeric_int_fields:
        if field in data and data[field] is not None and str(data[field]).strip() != "":
            try:
                student_input[field] = int(float(str(data[field])))
            except (ValueError, TypeError):
                pass

    for field in numeric_float_fields:
        if field in data and data[field] is not None and str(data[field]).strip() != "":
            try:
                student_input[field] = float(str(data[field]))
            except (ValueError, TypeError):
                pass

    if "degree" in data and data["degree"]:
        student_input["degree"] = str(data["degree"]).strip()

    # best_competition_rank = 0 means "never competed" → pass None
    if student_input.get("best_competition_rank", 0) == 0:
        student_input["best_competition_rank"] = None

    # ── Run prediction ──────────────────────────────────────────────────────
    try:
        from predict import predict_student
        result = predict_student(student_input)
    except FileNotFoundError as e:
        print(json.dumps({"error": f"Model file not found: {e}. Run: python src/train.py"}))
        sys.exit(1)
    except Exception as e:
        print(json.dumps({"error": f"Prediction failed: {e}"}))
        sys.exit(1)

    # ── Serialise numpy types ───────────────────────────────────────────────
    def _safe(obj):
        if hasattr(obj, "item"):
            return obj.item()
        return obj

    response = {
        "job_ready": _safe(result["job_ready"]),
        "job_ready_probability": round(_safe(result["job_ready_probability"]) * 100, 1),
        "career_track": _safe(result["career_track"]),
        "career_track_label": result["career_track_label"],
        "career_track_probabilities": {
            k: round(_safe(v) * 100, 1)
            for k, v in result["career_track_probabilities"].items()
        },
        "top_positive_factors": [
            {"feature": feat.replace("_", " ").title(), "value": _safe(val)}
            for feat, val in result["top_positive_factors"]
        ],
        "areas_to_improve": [
            feat.replace("_", " ").title() for feat in result["areas_to_improve"]
        ],
    }

    print(json.dumps(response))


if __name__ == "__main__":
    main()
