# Wrzucenie tego repozytorium na GitHuba

Repozytorium jest kompletne: dwa commity, tag `1.2.0`, autor ustawiony na
Łukasz Warcholik <l.warcholik@webdesign.net.pl>. Nie zostało wypchnięte,
bo moje środowisko nie ma dostępu do Twojego konta GitHub.

## Jeśli masz gh CLI

```bash
cd ai-disclosure-labels-repo
gh repo create ai-disclosure-labels --public --source=. --remote=origin --push
git push --tags
```

## Bez gh CLI

Założ puste repozytorium `ai-disclosure-labels` na github.com (bez README,
bez .gitignore, bez licencji — one już tu są), potem:

```bash
cd ai-disclosure-labels-repo
git remote add origin git@github.com:TWOJ-LOGIN/ai-disclosure-labels.git
git push -u origin main
git push --tags
```

## Sprawdź, czy commity są przypisane do Ciebie

```bash
git log --format='%an <%ae>'
```

Musi tam być adres, który masz dodany na koncie GitHub (Settings → Emails).
Jeśli wolisz nie pokazywać maila publicznie, włącz „Keep my email addresses
private", weź adres `ID+login@users.noreply.github.com` i przepisz historię:

```bash
git config user.email 'ID+login@users.noreply.github.com'
git rebase -r --root --exec 'git commit --amend --no-edit --reset-author'
```

## Potem

1. Settings → Secrets and variables → Actions: dodaj `SVN_USERNAME`
   i `SVN_PASSWORD` (dane z WordPress.org), gdy wtyczka zostanie przyjęta.
2. Dopisz prawdziwy login z WordPress.org w `readme.txt`, pole
   `Contributors:` (teraz jest placeholder `webdesignnetpl`).
3. Podmień `LICENSE.md` na pełny tekst GPL-2.0.
4. Dorób zrzuty ekranu, ikonę i banner — w SVN idą do katalogu `assets/`
   w korzeniu repozytorium SVN, nie do `trunk/assets/`.

Publikacja nowej wersji po skonfigurowaniu sekretów to jedno polecenie:

```bash
git tag 1.3.0 && git push --tags
```

Workflow sprawdzi składnię, uruchomi testy, porówna numer wersji w nagłówku
wtyczki i w `readme.txt` z tagiem, i dopiero wtedy wypchnie do SVN-a.
