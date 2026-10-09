# Wypchnięcie repozytorium

Repozytorium na GitHubie już istnieje i jest puste:
**https://github.com/lukaszwarcholik/ai-disclosure-labels**

Zdalne `origin` jest już ustawione w tym katalogu. Wystarczy:

```bash
cd ai-disclosure-labels-repo
git push -u origin main
git push --tags
```

Jeśli git zapyta o dane logowania, użyj GitHub CLI — raz i z głowy:

```bash
gh auth login        # przeglądarka, bez wklejania tokenów
```

albo zmień zdalne na SSH, jeśli masz wgrany klucz:

```bash
git remote set-url origin git@github.com:lukaszwarcholik/ai-disclosure-labels.git
```

## Sprawdź, czy commity są Twoje

```bash
git log --format='%an <%ae>'
```

Ma tam być `l.warcholik@webdesign.net.pl`. Ten adres musi być dodany na koncie
GitHub (Settings → Emails), inaczej commity nie będą przypisane do Ciebie.
Jeśli wolisz nie pokazywać maila publicznie, włącz „Keep my email addresses
private", weź adres `ID+lukaszwarcholik@users.noreply.github.com` i przepisz
historię:

```bash
git config user.email 'ID+lukaszwarcholik@users.noreply.github.com'
git rebase -r --root --exec 'git commit --amend --no-edit --reset-author'
git push --force-with-lease
```

## Potem, w tym samym katalogu

```bash
claude
/install-github-app
```

To instaluje aplikację Claude na repozytorium, zapisuje sekret i wypycha
gałąź z workflow. Po zmergowaniu tego PR-a piszesz `@claude` w issue albo
w komentarzu do PR-a i dostajesz zmiany bez siadania do kompa.
Wymaga `gh` i `gh auth login`.

## Do uzupełnienia przed zgłoszeniem na WordPress.org

1. Pełny tekst GPL-2.0 zamiast `LICENSE.md`.
2. Pięć zrzutów (`screenshot-1.png`…`screenshot-5.png`, opisy są w `readme.txt`),
   ikona 128/256 i banner 772×250 oraz 1544×500. W SVN idą do katalogu `assets/`
   w **korzeniu** repozytorium SVN, obok `trunk/` i `tags/` — nie do `trunk/assets/`.
3. Sekrety `SVN_USERNAME` i `SVN_PASSWORD` w Settings → Secrets → Actions,
   gdy wtyczka zostanie przyjęta.

Publikacja kolejnej wersji to wtedy jedno polecenie:

```bash
git tag 1.3.0 && git push --tags
```
