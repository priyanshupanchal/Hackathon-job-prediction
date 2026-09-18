# Project Demo Script (5-7 minutes)

**Presenter roles:** Shilpi leads, Vadika covers data, Dushant covers
modeling. Adjust timing to your actual slot.

---

## 1. Hook (30 sec) — Shilpi

> "Everyone assumes hackathon winners are automatically job-ready. We
> turned that assumption into a testable machine learning question: does
> hackathon participation actually predict job readiness — or do
> fundamentals, real projects, and internships matter more? Let's find
> out with data, not opinions."

## 2. The dataset (1 min) — Vadika

- Show `data_dictionary.csv` briefly — 28 features covering skills,
  projects, hackathons, internships, and interview performance.
- **Be upfront:** "This dataset is synthetic — we built it to demonstrate
  the full ML pipeline honestly, since we didn't have access to a real
  placement dataset. The generation logic and that disclaimer are in
  `data/README_DATA.md`."
- Show one EDA chart: the hackathon-vs-job-readiness boxplot from
  `01_eda.ipynb` — point out that the boxes barely shift, while the
  internship-months boxplot shows a real difference.

## 3. Preprocessing (30 sec) — Vadika

- "We engineered features like hackathon win rate and deployment rate,
  then built a preprocessing pipeline — median imputation, scaling,
  one-hot encoding — fit ONLY on the training set to avoid data
  leakage."

## 4. Modeling (1.5 min) — Dushant

- "We compared Logistic Regression, Decision Tree, Random Forest, and
  Gradient Boosting with 5-fold cross-validation, then tuned the best
  one with RandomizedSearchCV."
- State your actual test-set numbers from `models/model_metadata.json`
  (don't round up — report exactly what you got).
- "For career track, it's a 6-class problem — we used macro-F1 alongside
  accuracy since one class ('Not Yet Ready') is more common than others."

## 5. Explainability (1 min) — Dushant

- Show the permutation importance chart from `05_explainability.ipynb`.
- "The model itself — not us — ranked internship months, end-to-end
  projects, and interview scores above hackathon attendance. That
  matches our hypothesis, but importantly, we didn't force this
  conclusion — we built the ground truth with a deliberate weighting and
  then let the model recover it, which is a standard way to validate a
  methodology when real labeled data isn't yet available."

## 6. Live demo (1.5 min) — Shilpi

- Open the deployed Streamlit app.
- Enter a "hackathon collector" profile (many hackathons, weak
  fundamentals, no internship) → show the low readiness score.
- Enter a "balanced candidate" profile (moderate hackathons + strong
  fundamentals + internship) → show the higher score and the career
  track recommendation.
- Point at the "strongest factors" and "areas to improve" pills.

## 7. Close (30 sec) — Shilpi

> "Hackathons aren't useless — they build speed and collaboration skills.
> But our model shows they're not a substitute for fundamentals, real
> projects, and internship experience. That's the message we'd want
> every fresher to hear before over-indexing on hackathon count alone."

---

## Tips

- Rehearse the live demo inputs beforehand so you know exactly which
  numbers produce which outcome — don't improvise live.
- Have a backup: a screen recording of the app in case of wifi issues
  during deployment demos.
- Keep `models/model_metadata.json` open in a tab so you can quote exact
  metrics if asked.
