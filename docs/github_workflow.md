# GitHub Team Workflow (Windows PowerShell)

**For:** Shilpi (Team Lead), Vadika, Dushant
**Assumes:** Shilpi has already created the GitHub repo and added Vadika
and Dushant as collaborators.

---

## 0. Concepts, in plain language

- **Repository (repo):** the project's folder, tracked by Git, hosted on
  GitHub.
- **Clone:** downloading a copy of the repo to your own laptop.
- **Branch:** a parallel copy of the code where you make changes without
  touching the main, working version (`main`). Think of it as "my own
  scratch copy that I'll merge back in once it's ready."
- **Commit:** a saved checkpoint of your changes, with a message
  describing what you did.
- **Push:** uploading your commits from your laptop to GitHub.
- **Pull Request (PR):** a request asking "please merge my branch into
  `main`" — this is where Shilpi reviews the code before it becomes part
  of the real project.
- **Merge:** combining a branch's changes into `main`.
- **Merge conflict:** Git can't automatically combine two changes to the
  same lines of code — you resolve it by hand.

---

## 1. One-time setup (everyone)

```powershell
# Install Git if you don't have it: https://git-scm.com/download/win

# Configure your identity (once per laptop)
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"

# Clone the repository
git clone https://github.com/<team-org-or-username>/hackathon-employability-ml.git
cd hackathon-employability-ml
```

## 2. Open in VS Code

```powershell
code .
```

(If `code` isn't recognized, open VS Code, then File → Open Folder, and
select the cloned `hackathon-employability-ml` folder.)

## 3. Set up your Python environment

```powershell
python -m venv venv
.\venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

## 4. Create a branch for your task

Each person works on their OWN branch — never commit directly to `main`.

```powershell
# Make sure you're up to date first
git checkout main
git pull origin main

# Create and switch to your feature branch
git checkout -b feature/eda          # Vadika, for example
```

**Suggested branch names per person:**
- Vadika: `feature/eda`, `feature/preprocessing`
- Dushant: `feature/models`, `feature/explainability`
- Shilpi: `feature/app`, `feature/deployment`

## 5. Make your changes, then commit

```powershell
git add .
git commit -m "Add EDA notebook with hackathon vs job-readiness analysis"
```

**Good commit message habits:** short, present-tense, describes WHAT
changed — e.g. `"Add hyperparameter tuning for RandomForest"`, not
`"updates"` or `"fix"`.

## 6. Push your branch to GitHub

```powershell
git push origin feature/eda
```

(First time pushing a new branch, Git may print a suggested command —
copy-paste it if `git push` alone doesn't work.)

## 7. Create a Pull Request

1. Go to the repo on GitHub.com — you'll see a banner "Compare & pull
   request" for your recently pushed branch. Click it.
2. Write a short description of what you changed and why.
3. Assign **Shilpi** as the reviewer.
4. Click **Create pull request**.

## 8. Code review (Shilpi's job, but everyone should read this)

- Shilpi opens the "Files changed" tab, reads through the diff.
- Leave comments on specific lines if something needs fixing.
- If it looks good: click **Merge pull request** → **Confirm merge**.
- Delete the branch after merging (GitHub will offer a button) to keep
  things tidy.

## 9. Everyone: sync after a merge

After ANY teammate's PR is merged into `main`, everyone should update
their local `main` before starting new work:

```powershell
git checkout main
git pull origin main
```

## 10. Resolving merge conflicts

If Git says there's a conflict when merging:

```powershell
git checkout main
git pull origin main
git checkout feature/your-branch
git merge main
```

Git will mark the conflicting sections in the file like this:

```
<<<<<<< HEAD
your version of the code
=======
the other version of the code
>>>>>>> main
```

Manually edit the file to keep the correct version (or combine both),
delete the `<<<<<<<`, `=======`, `>>>>>>>` markers, then:

```powershell
git add .
git commit -m "Resolve merge conflict in src/preprocessing.py"
git push origin feature/your-branch
```

## 11. Running the project locally (after cloning/pulling)

```powershell
# Activate your virtual environment first
.\venv\Scripts\Activate.ps1

# Generate the dataset (if not already committed)
python src/generate_dataset.py

# Run preprocessing
python src/preprocessing.py

# Train models
python src/train.py

# Run tests
pytest tests/
# or, if pytest isn't installed:
python tests/test_preprocessing.py
python tests/test_predict.py

# Launch the dashboard
streamlit run app/streamlit_app.py
```

## 12. Branch protection (Shilpi sets this up once)

On GitHub: **Settings → Branches → Add branch protection rule** for
`main`:
- Require a pull request before merging ✅
- Require at least 1 approval ✅

This physically prevents anyone (including Shilpi) from pushing straight
to `main` by accident.
