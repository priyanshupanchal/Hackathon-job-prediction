# Resume Bullet Points

Pick 2-3 per person — tailor numbers to YOUR actual results from
`models/model_metadata.json`.

## For everyone (team-level bullet)

- Built and deployed an end-to-end ML system predicting entry-level Data/AI
  job readiness and recommending career tracks, using a leakage-safe
  scikit-learn pipeline, explainable AI (SHAP/permutation importance), and
  a Streamlit dashboard deployed on Streamlit Community Cloud.

## Shilpi (Team Lead / ML Integration / Deployment)

- Led a 3-person team building a full-stack ML project from raw data to
  cloud deployment; owned architecture decisions, GitHub branch strategy,
  and code review for all pull requests.
- Designed and built an interactive Streamlit dashboard integrating a
  trained classification pipeline, real-time predictions, and
  model-driven explainability, deployed on Streamlit Community Cloud.
- Set up CI-friendly project structure (src/, notebooks/, tests/, app/)
  and wrote a reusable prediction API (`predict_student()`) shared
  between the app and automated tests.

## Vadika (Data Engineer / EDA)

- Designed and documented a 28-feature synthetic dataset (1,200 records)
  with an explicit data dictionary and data-integrity disclosure,
  demonstrating responsible synthetic-data practices.
- Built a leakage-safe preprocessing pipeline (median/mode imputation,
  standard scaling, one-hot encoding) using scikit-learn's
  ColumnTransformer, fit exclusively on the training split.
- Engineered 11 derived features (e.g., hackathon win rate, deployment
  rate, aggregate skill/interview indices) that improved model
  interpretability without leaking target information.

## Dushant (ML Engineer / Explainable AI)

- Trained and compared 4 classification algorithms (Logistic Regression,
  Decision Tree, Random Forest, Gradient Boosting) across two prediction
  tasks (binary job-readiness, 6-class career-track) using stratified
  5-fold cross-validation and RandomizedSearchCV tuning.
- Achieved [X]% ROC-AUC / [X]% accuracy on held-out test data for binary
  job-readiness classification — *(fill in your real numbers)*.
- Implemented model-agnostic explainability (permutation importance,
  built-in feature importance, with a SHAP integration path) to surface
  which features most influence predictions, avoiding fabricated or
  assumed feature rankings.
- Conducted systematic error analysis to identify the model's most
  confident misclassifications, informing future feature engineering.
