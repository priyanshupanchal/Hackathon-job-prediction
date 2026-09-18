"""
generate_dataset.
 (Data Engineer)

Purpose:
    Generates a SYNTHETIC dataset for the Hackathon Success & AI Career
    Readiness Prediction project.

    IMPORTANT / DATA INTEGRITY NOTICE:
    -----------------------------------
    This dataset is 100% SYNTHETIC. It is generated using a hand-designed
    scoring formula plus random noise, NOT collected from real students or
    real placement outcomes. It exists only to demonstrate the ML
    methodology (EDA, preprocessing, classification, explainability,
    deployment).

    DO NOT present results from this dataset as real-world evidence that
    hackathons do or do not improve employability. If you get access to a
    real, anonymized survey/placement dataset later, swap it in — the rest
    of the pipeline (preprocessing, training, app) does not need to change
    as long as the column names match.

    Design choice (deliberately built into the generator, and worth stating
    in your report): fundamentals (ML/DL/SQL/Stats), end-to-end + deployed
    projects, internship months, and interview/communication scores are
    given MUCH higher weight than hackathons_attended. hackathons_won gets
    a small positive nudge. This encodes the project's core hypothesis
    ("hackathon participation is evidence of exposure, not proof of
    expertise") into the ground truth so the trained model has a genuine,
    non-trivial relationship to discover and explain — it does NOT mean
    the number is rigged to reach a foregone conclusion; noise and
    overlapping distributions are added so no single feature is
    deterministic.

Output:
    data/raw/student_employability.csv
"""

import numpy as np
import pandas as pd

RANDOM_SEED = 42
N_SAMPLES = 1200

rng = np.random.default_rng(RANDOM_SEED)


def generate_dataset(n=N_SAMPLES, seed=RANDOM_SEED):
    rng = np.random.default_rng(seed)

    degrees = rng.choice(
        ["B.Tech", "B.Sc", "M.Tech", "MCA", "BCA"],
        size=n,
        p=[0.55, 0.15, 0.10, 0.12, 0.08],
    )

    age = rng.integers(20, 26, size=n)
    cgpa = np.clip(rng.normal(7.5, 0.9, size=n), 5.0, 10.0).round(2)

    # --- Skill scores (0-10) ---
    python_score = np.clip(rng.normal(6.5, 1.8, size=n), 0, 10).round(1)
    sql_score = np.clip(rng.normal(5.8, 2.0, size=n), 0, 10).round(1)
    statistics_score = np.clip(rng.normal(5.5, 2.0, size=n), 0, 10).round(1)
    ml_score = np.clip(rng.normal(5.8, 2.0, size=n), 0, 10).round(1)
    dl_score = np.clip(rng.normal(4.5, 2.2, size=n), 0, 10).round(1)
    genai_score = np.clip(rng.normal(5.0, 2.2, size=n), 0, 10).round(1)

    # --- Project & competition evidence ---
    ml_projects = rng.poisson(2.5, size=n)
    end_to_end_projects = np.minimum(
        rng.poisson(1.2, size=n), ml_projects
    )  # can't exceed total ml projects
    deployed_projects = np.minimum(
        rng.poisson(0.6, size=n), end_to_end_projects
    )
    kaggle_competitions = rng.poisson(1.5, size=n)
    best_competition_rank = np.where(
        kaggle_competitions > 0,
        rng.integers(1, 500, size=n),
        np.nan,
    )
    github_projects = rng.poisson(4, size=n)

    # --- Hackathon evidence ---
    hackathons_attended = rng.poisson(3, size=n)
    hackathons_won = np.minimum(
        rng.binomial(hackathons_attended, 0.15), hackathons_attended
    )
    finalist_status = np.minimum(
        rng.binomial(hackathons_attended, 0.25) + hackathons_won,
        hackathons_attended,
    )

    # --- Real-world exposure ---
    internship_months = rng.poisson(2, size=n)
    certifications = rng.poisson(2.5, size=n)

    # --- Interview / soft-skill signals ---
    ml_interview_score = np.clip(rng.normal(5.5, 2.0, size=n), 0, 10).round(1)
    communication_score = np.clip(rng.normal(6.5, 1.8, size=n), 0, 10).round(1)
    dsa_score = np.clip(rng.normal(5.5, 2.0, size=n), 0, 10).round(1)
    resume_score = np.clip(rng.normal(6.0, 1.8, size=n), 0, 10).round(1)
    mock_interview_score = np.clip(rng.normal(5.8, 2.0, size=n), 0, 10).round(1)

    # ------------------------------------------------------------------
    # Ground-truth "job readiness" score.
    # Weights encode the project hypothesis: fundamentals + real project
    # evidence + internship + interview performance matter far more than
    # raw hackathon attendance. This is a DESIGN CHOICE for a synthetic
    # demo dataset, not a claim about real hiring.
    # ------------------------------------------------------------------
    score = (
        1.1 * python_score
        + 0.9 * sql_score
        + 0.8 * statistics_score
        + 1.5 * ml_score
        + 0.9 * dl_score
        + 0.6 * genai_score
        + 2.2 * end_to_end_projects
        + 2.8 * deployed_projects
        + 0.6 * ml_projects
        + 1.1 * kaggle_competitions
        + 2.4 * internship_months
        + 0.3 * certifications
        + 1.8 * ml_interview_score
        + 1.1 * communication_score
        + 0.9 * dsa_score
        + 0.7 * resume_score
        + 1.6 * mock_interview_score
        + 0.08 * hackathons_attended       # small weight: attendance alone
        + 0.5 * hackathons_won             # slightly more weight: winning
        + 0.15 * finalist_status
        + rng.normal(0, 3.0, size=n)       # noise so it's not deterministic
    )

    # Convert to probability via logistic function, then threshold
    prob_ready = 1 / (1 + np.exp(-(score - np.median(score)) / 5))
    job_ready = (rng.random(n) < prob_ready).astype(int)

    # ------------------------------------------------------------------
    # Career track (only meaningful when job_ready == 1; else "Not Yet Ready")
    # Based on which fundamental skill cluster is strongest for that student.
    # ------------------------------------------------------------------
    track_scores = np.vstack(
        [
            (sql_score + statistics_score) / 2,                                  # 0 Data Analyst
            (statistics_score + ml_score + python_score) / 3,                    # 1 Data Scientist
            (ml_score + end_to_end_projects * 2 + deployed_projects * 2),        # 2 ML Engineer
            (dl_score * 1.5 + ml_score) / 2,                                      # 3 DL Engineer
            (genai_score * 1.5 + python_score) / 2,                              # 4 GenAI Engineer
        ]
    ).T
    # Standardize each track's raw score (z-score) so tracks built from
    # different scales/units compete on equal footing, then add small noise
    # for realistic overlap between career paths.
    track_scores = (track_scores - track_scores.mean(axis=0)) / track_scores.std(axis=0)
    track_scores = track_scores + rng.normal(0, 0.6, size=track_scores.shape)
    best_track = track_scores.argmax(axis=1)

    career_track = np.where(job_ready == 1, best_track, 5)  # 5 = Not Yet Ready

    df = pd.DataFrame(
        {
            "student_id": [f"S{100000 + i}" for i in range(n)],
            "age": age,
            "degree": degrees,
            "cgpa": cgpa,
            "python_score": python_score,
            "sql_score": sql_score,
            "statistics_score": statistics_score,
            "ml_score": ml_score,
            "dl_score": dl_score,
            "genai_score": genai_score,
            "ml_projects": ml_projects,
            "end_to_end_projects": end_to_end_projects,
            "deployed_projects": deployed_projects,
            "kaggle_competitions": kaggle_competitions,
            "best_competition_rank": best_competition_rank,
            "hackathons_attended": hackathons_attended,
            "hackathons_won": hackathons_won,
            "finalist_status": finalist_status,
            "github_projects": github_projects,
            "internship_months": internship_months,
            "certifications": certifications,
            "ml_interview_score": ml_interview_score,
            "communication_score": communication_score,
            "dsa_score": dsa_score,
            "resume_score": resume_score,
            "mock_interview_score": mock_interview_score,
            "job_ready": job_ready,
            "career_track": career_track,
        }
    )

    # Introduce a small % of realistic missing values (common in surveys)
    for col in ["cgpa", "communication_score", "certifications", "resume_score"]:
        mask = rng.random(n) < 0.03
        df.loc[mask, col] = np.nan

    return df


if __name__ == "__main__":
    df = generate_dataset()
    out_path = "data/raw/student_employability.csv"
    df.to_csv(out_path, index=False)
    print(f"Saved {len(df)} rows to {out_path}")
    print(df["job_ready"].value_counts(normalize=True).rename("proportion"))
    print(df["career_track"].value_counts().sort_index())
