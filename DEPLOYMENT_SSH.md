# Nastavenie SSH Deploy Key pre Private Repozitár

Tento dokument popisuje, ako nastaviť SSH Deploy Key pre automatické deployovanie private repozitára na serveri.

## Prečo SSH Deploy Key?

- ✅ **Bezpečnejšie** ako Personal Access Token (PAT) - prístup len k jednému repozitáru
- ✅ **Bez nutnosti prepínať** repozitár medzi public/private
- ✅ **Automatické deployy** bez manuálnych hesiel

## Krok 1: Pridať SSH kľúč do GitHubu

### 1.1 Zobraziť verejný kľúč

Na serveri spustite:
```bash
cat ~/.ssh/github_deploy_key.pub
```

Skopírujte celý výstup (začína `ssh-ed25519 ...`).

### 1.2 Pridať Deploy Key do GitHubu

1. Otvorte GitHub repozitár: https://github.com/webiumsk/D21Panel
2. Prejdite na **Settings** → **Deploy keys**
3. Kliknite na **Add deploy key**
4. Vyplňte:
   - **Title**: `uzol21-production-server` (alebo ľubovoľný názov)
   - **Key**: Vložte skopírovaný verejný kľúč
   - ✅ **Allow write access** - **ZAŠKRKNITE** ak chcete pushovať zmeny z servera
     - Len čítanie je bezpečnejšie, ale ak chcete automaticky pushnúť deploy commity, musíte povoliť write access
5. Kliknite na **Add key**

**Poznámka:** Ak ste už vytvorili deploy key bez write access a chcete pridať push možnosti:
1. Choďte na Settings → Deploy keys
2. Nájdite váš kľúč a kliknite na **Edit**
3. Zaškrtnite **Allow write access**
4. Kliknite **Update key**

### 1.3 Overenie

Na serveri spustite:
```bash
ssh -T git@github.com
```

Mali by ste vidieť:
```
Hi webiumsk/D21Panel! You've successfully authenticated...
```

**Poznámka:** Môže sa zobraziť "but GitHub does not provide shell access" - to je v poriadku.

## Krok 2: Test Git prístupu

```bash
cd /home/peterhorvath/apps/bitcoin/D21Panel
git fetch origin
git status
```

Ak funguje bez chýb, SSH kľúč je správne nastavený! 🎉

## Krok 3: Použitie v deploy.sh

`deploy.sh` automaticky používa SSH, ak je remote URL nastavený na SSH formát:
```bash
git remote -v
# Malo by ukázať:
# origin  git@github.com:webiumsk/D21Panel.git (fetch)
# origin  git@github.com:webiumsk/D21Panel.git (push)
```

Ak stále vidíte HTTPS URL, zmeňte ho:
```bash
git remote set-url origin git@github.com:webiumsk/D21Panel.git
```

## Riešenie problémov

### Problém: "Host key verification failed"
```bash
ssh-keyscan github.com >> ~/.ssh/known_hosts
```

### Problém: "Permission denied (publickey)"
- Skontrolujte, či je kľúč pridaný v GitHub → Settings → Deploy keys
- Overte, či máte správne oprávnenia na `~/.ssh/github_deploy_key`:
  ```bash
  chmod 600 ~/.ssh/github_deploy_key
  ```

### Problém: "Could not read Username for 'https://github.com'"
- Zmeňte remote URL na SSH formát (pozri Krok 3)

## Alternatíva: Personal Access Token (PAT)

Ak preferujete PAT namiesto SSH:

1. Vytvorte token na GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Vyberte oprávnenia: `repo` (full control)
3. Skopírujte token
4. Upravte remote URL:
   ```bash
   git remote set-url origin https://<TOKEN>@github.com/webiumsk/D21Panel.git
   ```

**Poznámka:** PAT je menej bezpečný (prístup k všetkým repozitárom), odporúčame SSH Deploy Key.

