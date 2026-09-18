# Cloud Deployment Guide

**Owner:** Shilpi (Team Lead)

This project is designed to run on **Streamlit Community Cloud** — free,
and it deploys directly from your GitHub repository.

## 1. Prerequisites

- Your code is pushed to a GitHub repository (public or private).
- `requirements.txt` is present at the repo root (already included).
- The trained model files exist under `models/` — either:
  - **Option A (simplest):** commit them to GitHub (they're a few MB —
    fine for a student project). Remove the `models/*.joblib` line from
    `.gitignore` if you go this route.
  - **Option B:** have the app train models on first load if they're
    missing (more complex, not needed for this project's scale).

We recommend **Option A** for this project.

## 2. Steps — Streamlit Community Cloud

1. Go to **https://share.streamlit.io** and sign in with GitHub.
2. Click **"New app"**.
3. Select your repository, branch (`main`), and set:
   - **Main file path:** `app/streamlit_app.py`
4. Click **Deploy**.
5. Streamlit Cloud installs everything in `requirements.txt` and starts
   your app. First deploy takes 2-5 minutes.
6. You'll get a public URL like:
   `https://<your-app-name>.streamlit.app`
7. Every time you push to `main`, the app **automatically redeploys**.

## 3. Secrets handling

This project does **not** require any API keys or secrets — the model
runs entirely locally within the app. If you later add something that
needs a secret (e.g. a hosted database), add it under your app's
**Settings → Secrets** on Streamlit Cloud (never commit secrets to
GitHub).

## 4. Troubleshooting

| Problem | Likely cause | Fix |
|---|---|---|
| `ModuleNotFoundError: No module named 'src'` or similar import errors | App can't find `src/` modules | Confirm `app/streamlit_app.py` has `sys.path.append(...)` pointing to `src/` (already included) |
| "Model files not found" error in the app | `models/*.joblib` weren't committed | Remove `models/*.joblib` from `.gitignore`, commit the model files, push again |
| App builds but times out / crashes | A package in `requirements.txt` failed to install (e.g. `shap` build issues on some platforms) | Comment out `shap` — the code automatically falls back to permutation importance |
| Old version keeps showing after you push new code | Streamlit Cloud cache | Use the "Reboot app" option in the app's menu (top-right, on share.streamlit.io) |

## 5. Alternatives (if you outgrow the free tier or want more control)

- **Hugging Face Spaces** — also free, supports Streamlit directly.
  Create a Space, choose "Streamlit" as the SDK, and push your repo the
  same way (a `README.md` with Space metadata at the top is required —
  see Hugging Face's Spaces docs).
- **AWS / Azure / Google Cloud** — only needed if you require custom
  infrastructure, autoscaling, or a private VPC. Typically deployed as a
  Docker container behind a small managed service (e.g. AWS App Runner,
  Azure Container Apps, GCP Cloud Run). Not necessary for this project's
  scale — mentioned here only as the "grown-up" path if this becomes a
  real product later.

**Do not assume paid cloud services are required** — the free tier of
Streamlit Community Cloud is sufficient for this entire project.
