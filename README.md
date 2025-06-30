# UNI PORTAL SYSTEM BACKEND

This repository contains the frontend codebase for the ESUT Open and Distance Learning Portal, built using Next.js.

---

## 🧑‍💻 Developer Workflow

To maintain clean code and organized collaboration, please follow the workflow below:

### 1. Pull Latest `develop` Branch

```bash
git checkout develop
git pull origin develop
```

### 2. Create a Feature Branch

Name your branch clearly using the format: `feature/your-feature-name`

```bash
git checkout -b feature/add-student-profile
```

### 3. Make Your Changes

Work on your feature and commit regularly:

```bash
git add .
git commit -m "feat: add student profile form"
```

### 4. Push to GitHub

```bash
git push -u origin feature/add-student-profile
```

### 5. Open a Pull Request (PR)

- Go to the GitHub repository
- Create a PR **from your feature branch into `develop`**
- Add a clear title and description
- Request a review from a teammate

### 6. Merge PR into `develop`

Once reviewed and approved, the PR can be merged into `develop`.

> ⚠️ Only Admins can merge `develop` into `main`.

---

## 🔁 Branching Strategy

| Branch       | Purpose                                | Who Can Push  |
|--------------|----------------------------------------|----------------|
| `main`       | Production-ready code                  | ✅ Admin only   |
| `develop`    | Active development integration branch  | ✅ All developers |
| `feature/*`  | Individual features or fixes           | ✅ Creator only  |

---

## 🔐 Branch Protection Rules

| Branch    | Protected | PR Required | Direct Push Blocked | Merge Restricted |
|-----------|-----------|-------------|----------------------|------------------|
| `main`    | ✅         | ✅           | ✅                    | ✅ Admin only     |
| `develop` | ✅         | ✅           | ✅                    | ❌ Open to devs   |

---

## ✅ Git Naming Conventions

- **Feature**: `feature/add-login-form`
- **Bugfix**: `bugfix/fix-header-alignment`
- **Hotfix**: `hotfix/reset-password-issue`

---

## ✅ Commit Message Format

Examples:
- `feat: add student enrollment form`
- `fix: correct grade calculation bug`
- `chore: update dependencies`

---

## 📦 Tech Stack

- **Framework**: Next.js
- **UI**: Tailwind CSS, ShadCN UI
- **State Management**: React Query
- **Form Handling**: React Hook Form + Zod
- **Auth**: NextAuth
- **Others**: TypeScript, ESLint, Prettier

---

## 👥 Team Roles

| Name             | Role       |
|------------------|------------|
| `Admin`          | Repo Maintainer & Final Merge |
| `Developers`     | Feature Builders & Reviewers  |

---

## 📬 Need Help?

Please open an [Issue](https://github.com/qverselearningdev/esut-odl-portal-frontend/issues) 
