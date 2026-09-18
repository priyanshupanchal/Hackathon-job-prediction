# Viva Questions & Answers

Prepare to answer these in your own words — don't memorize verbatim,
understand the reasoning so follow-up questions don't trip you up.

### Why did you choose this problem?

Hackathon participation is often treated as proof of employability, but
that's an assumption, not a validated fact. We wanted to turn it into a
measurable ML problem: given a learner's full profile (skills, projects,
internships, interviews, AND hackathons), can we predict job readiness,
and which factors actually drive that prediction?

### Why this dataset?

We didn't have access to a real, anonymized placement dataset at project
start, so we built a clearly labeled **synthetic** dataset (documented in
`data/README_DATA.md`) to demonstrate the complete methodology honestly.
The generation logic deliberately weights fundamentals and real project
evidence above hackathon attendance, then adds noise — this lets us
validate that our pipeline (EDA → preprocessing → modeling →
explainability) can recover a known, non-trivial signal. If we had real
data, we'd swap it in without changing the rest of the pipeline.

### Why this model?

For `job_ready`, we compared Logistic Regression, Decision Tree, Random
Forest, and Gradient Boosting via cross-validation, then tuned Random
Forest with RandomizedSearchCV because tree ensembles handle mixed
numeric/categorical, nonlinear feature interactions well without heavy
manual feature engineering. For `career_track`, we picked whichever
model had the best cross-validated macro-F1 (report which one won in
your actual run — check `models/model_metadata.json`).

### Why this evaluation metric?

Accuracy alone can be misleading — our `job_ready` classes are fairly
balanced, but `career_track` has one dominant class ("Not Yet Ready"), so
we also report macro-F1, which weights all classes equally regardless of
how common they are. For binary classification we also track ROC-AUC and
PR-AUC since they evaluate ranking quality across all thresholds, not
just one cutoff.

### What failed and what did you change?

(Answer based on your actual experience — some real examples from
building this:)
- Our first version of the synthetic data generator added too much
  random noise, which washed out every feature's correlation with the
  target (max correlation ~0.15). We reduced the noise and increased the
  weight gap between fundamentals and hackathon-only features so the
  model would have real signal to learn from and explain.
- Our first career-track logic let one skill cluster dominate due to
  unequal scoring, producing a highly imbalanced multi-class target. We
  fixed this by standardizing (z-scoring) each track's score before
  taking the argmax.

### How did you prevent data leakage?

We split into train/test BEFORE fitting the preprocessing pipeline
(imputer, scaler, encoder) — the pipeline is `.fit()` only on the
training set, then reused via `.transform()` on the test set and later
on live app inputs. We also used stratified cross-validation during
model selection so class balance stays consistent across folds.

### How would you deploy the model?

We deploy the Streamlit app to Streamlit Community Cloud, which builds
directly from our GitHub repo's `requirements.txt` and runs
`app/streamlit_app.py`. The trained model files are committed alongside
the code so no separate training step is needed at deploy time.

### How would you monitor it in production?

We'd track: (1) input distribution drift — are new users' profiles
similar to training data, or very different; (2) prediction distribution
over time — is the % predicted "job-ready" drifting unexpectedly; (3)
once real outcomes are available, actual accuracy/calibration against
those outcomes, to detect model decay and trigger retraining.

### What business or user decision does the model enable?

A student can see, before applying for jobs, which specific areas
(interview prep, deployment experience, internships, etc.) would most
improve their estimated readiness — turning a vague "am I ready?"
worry into concrete, prioritized next steps.

### Does hackathon participation help or not — what's your actual conclusion?

Our model's explainability results show hackathon attendance and even
wins ranked well below fundamentals, real project experience, and
internships in driving the job-readiness prediction (see
`05_explainability.ipynb` for the actual ranking from your run).
**Important caveat:** because our training data is synthetic and this
relationship was partly designed into the data generator, this
demonstrates the methodology rather than proving the claim about real
hiring. With real data, the same pipeline would let the evidence — not
an assumption — answer the question.
