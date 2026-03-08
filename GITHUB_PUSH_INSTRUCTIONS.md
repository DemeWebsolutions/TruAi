# Push TruAi Pilot Milestone to GitHub

**Remotes configured:**
- **TruAi (this repo):** `origin` → `git@github.com:DemeWebsolutions/TruAi.git`
- **Tru.ai (separate repo):** `git@github.com:DemeWebsolutions/Tru.ai.git` — use if you push the Electron app to its own repo.

## Push this project (TruAi)

From the project root:

```bash
cd "/Users/mydemellc./Desktop/TruAi"
git push -u origin main
```

### If you get "Permission denied (publickey)"

GitHub is using SSH; your Mac needs an SSH key added to your GitHub account.

1. **Check for an existing key:**  
   `ls -la ~/.ssh/*.pub`  
   If you see `id_ed25519.pub` or `id_rsa.pub`, use it in step 3.

2. **Create a new key (if needed):**  
   `ssh-keygen -t ed25519 -C "your_email@example.com"`  
   Press Enter to accept the default path; set a passphrase or leave empty.

3. **Add the public key to GitHub:**  
   - Copy: `cat ~/.ssh/id_ed25519.pub` (or `id_rsa.pub`)  
   - GitHub → Settings → SSH and GPG keys → New SSH key → paste and save.

4. **Test:**  
   `ssh -T git@github.com`  
   Then run `git push -u origin main` again.

### Alternative: use HTTPS with a Personal Access Token

If you prefer HTTPS (GitHub no longer accepts account passwords):

```bash
git remote set-url origin https://github.com/DemeWebsolutions/TruAi.git
git push -u origin main
```

When prompted for password, use a [Personal Access Token](https://github.com/settings/tokens) (classic) with `repo` scope, not your GitHub password.

## Tru.ai repo (Electron app only)

If you maintain a separate **Tru.ai** repo for the packaged Electron app:

- Repo: https://github.com/DemeWebsolutions/Tru.ai  
- SSH: `git@github.com:DemeWebsolutions/Tru.ai.git`

To add it as a second remote and push the `electron/` folder only, you’d typically use a separate clone of Tru.ai and copy in the contents of `electron/` (and any build outputs), then commit and push from that clone.

## Verify after push

- On GitHub, latest commit should be **"TruAi Pilot Milestone Complete"** (or the docs commit after it).
- Confirm `assets/images/`, `backend/`, `public/TruAi/`, and `electron/` are in the repo.
