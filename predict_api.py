"""
predict_api.py
--------------
Lightweight Flask API that bridges the PHP front-end with the Python ML models.

Start it with:
    python predict_api.py
    (or: venv/Scripts/python predict_api.py)

It listens on http://127.0.0.1:5001

PHP pages call:
    POST /predict  →  JSON body with student features  →  JSON prediction result
"""

import os
import sys
import json
from flask import Flask, request, jsonify
from flask_cors import CORS

# Make src/ importable
sys.path.insert(0, os.path.join(os.path.dirname(__file__), "src"))

from predict import predict_student  # noqa: E402

app = Flask(__name__)
CORS(app, origins=["http://localhost", "http://127.0.0.1"])  # allow PHP on same host


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok", "message": "Prediction API is running"})


@app.route("/predict", methods=["POST"])
def predict():
    """
    Accepts JSON body with student features and returns prediction.

    Required JSON fields (all optional — defaults are applied for missing ones):
        age, cgpa, degree,
        python_score, sql_score, statistics_score, ml_score, dl_score, genai_score,
        ml_projects, end_to_end_projects, deployed_projects,
        kaggle_competitions, best_competition_rank,
        hackathons_attended, hackathons_won, finalist_status,
        github_projects, internship_months, certifications,
        ml_interview_score, communication_score, dsa_score,
        resume_score, mock_interview_score
    """
    if not request.is_json:
        return jsonify({"error": "Content-Type must be application/json"}), 400

    data = request.get_json(silent=True)
    if data is None:
        return jsonify({"error": "Invalid JSON body"}), 400

    # Cast numeric strings to proper types (PHP json_encode sends numbers as-is)
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

    # best_competition_rank = 0 means "never competed" -> pass None
    if student_input.get("best_competition_rank", 0) == 0:
        student_input["best_competition_rank"] = None

    try:
        result = predict_student(student_input)
    except FileNotFoundError as e:
        return jsonify({"error": f"Model file not found: {str(e)}. Run python src/train.py first."}), 500
    except Exception as e:
        return jsonify({"error": f"Prediction failed: {str(e)}"}), 500

    # Convert numpy types to native Python for JSON serialisation
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

    return jsonify(response)


if __name__ == "__main__":
    print("=" * 60)
    print("  Hackathon Career Readiness - Prediction API")
    print("  Listening on http://127.0.0.1:5001")
    print("  Press Ctrl+C to stop.")
    print("=" * 60)
    app.run(host="127.0.0.1", port=5001, debug=False)
