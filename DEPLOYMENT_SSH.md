# Nastavenie SSH Deploy Key pre Private Repozitár

Tento dokument popisuje, ako nastaviť SSH Deploy Key pre automatické deployovanie private repozitára na **produkčnom serveri**.

**⚠️ Dôležité:** Tieto kroky sa vykonávajú **NA SERVERI**, nie na localhoste!

## Prečo SSH Deploy Key?

- ✅ **Bezpečnejšie** ako Personal Access Token (PAT) - prístup len k jednému repozitáru
- ✅ **Bez nutnosti prepínať** repozitár medzi public/private
- ✅ **Automatické deployy** bez manuálnych hesiel

## Krok 1: Vytvoriť SSH kľúč na serveri

### 1.1 Prihlásiť sa na server

Najprv sa prihlási na produkčný server:

```bash
ssh uzivatel@tvoj-server.sk
# alebo ak máš priamy prístup k serveru
```

### 1.2 Rozhodnúť sa, ako používateľ spúšťa deploy

**Otázka:** Ako používateľ beží deploy script na serveri?

- Ak ako **root** (cez `sudo`): vytvor kľúč pre root
- Ak ako **iný používateľ** (napr. `peterhorvath`): vytvor kľúč pre toho používateľa

### 1.3 Vygenerovať SSH kľúč

Na serveri spustite (ako používateľ, ktorý bude spúšťať deploy):

```bash
# Pre root používateľa:
sudo ssh-keygen -t ed25519 -C "satflux.io-deploy@server" -f ~/.ssh/github_deploy_key -N ""

# Alebo pre iného používateľa (napr. peterhorvath):
ssh-keygen -t ed25519 -C "satflux.io-deploy@server" -f ~/.ssh/github_deploy_key -N ""
```

### 1.4 Zobraziť verejný kľúč

**Na serveri** zobraz verejný kľúč:

```bash
# Pre root:
sudo cat ~/.ssh/github_deploy_key.pub

# Alebo pre iného používateľa:
cat ~/.ssh/github_deploy_key.pub
```

Skopírujte celý výstup (začína `ssh-ed25519 ...`).

### 1.3 Nastavenie kľúča pre root (ak deploy beží ako root)

Ak ste vytvorili kľúč pre iného používateľa (napr. `peterhorvath`), ale deploy script beží ako root, skopírujte kľúč:

```bash
sudo bash << 'ROOTSCRIPT'
mkdir -p /root/.ssh
chmod 700 /root/.ssh

# Skopíruj verejný kľúč
cat /home/peterhorvath/.ssh/github_deploy_key.pub > /root/.ssh/github_deploy_key.pub
chmod 644 /root/.ssh/github_deploy_key.pub

# Skopíruj súkromný kľúč
cat /home/peterhorvath/.ssh/github_deploy_key > /root/.ssh/github_deploy_key
chmod 600 /root/.ssh/github_deploy_key

# Vytvor SSH config
cat > /root/.ssh/config << 'EOFCONFIG'
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/github_deploy_key
    IdentitiesOnly yes
EOFCONFIG
chmod 600 /root/.ssh/config

# Pridaj GitHub do known_hosts
ssh-keyscan github.com >> /root/.ssh/known_hosts 2>/dev/null

echo "✓ SSH kľúč nastavený pre root"
cat /root/.ssh/github_deploy_key.pub
ROOTSCRIPT
```

## Krok 2: Pridať SSH kľúč do GitHubu

### 2.1 Pridať Deploy Key do GitHubu

1. Otvorte GitHub repozitár: https://github.com/webiumsk/D21Panel
2. Prejdite na **Settings** → **Deploy keys**
3. Kliknite na **Add deploy key**
4. Vyplňte:
   - **Title**: `satflux.io-production-server` (alebo ľubovoľný názov)
   - **Key**: Vložte skopírovaný verejný kľúč
   - ✅ **Allow write access** - **ZAŠKRKNITE** ak chcete pushovať zmeny z servera
     - Len čítanie je bezpečnejšie, ale ak chcete automaticky pushnúť deploy commity, musíte povoliť write access
5. Kliknite na **Add key**

**Poznámka:** Ak ste už vytvorili deploy key bez write access a chcete pridať push možnosti:

1. Choďte na Settings → Deploy keys
2. Nájdite váš kľúč a kliknite na **Edit**
3. Zaškrtnite **Allow write access**
4. Kliknite **Update key**

### 2.2 Overenie

Na serveri spustite (ako používateľ, ktorý má kľúč):

```bash
# Pre root:
ssh -T git@github.com

# Alebo pre iného používateľa:
# ssh -T git@github.com
```

Mali by ste vidieť:

```
Hi webiumsk/D21Panel! You've successfully authenticated...
```

**Poznámka:** Môže sa zobraziť "but GitHub does not provide shell access" - to je v poriadku.

## Krok 3: Test Git prístupu

```bash
cd /home/peterhorvath/apps/bitcoin/D21Panel
git fetch origin
git status
```

Ak funguje bez chýb, SSH kľúč je správne nastavený! 🎉

## Krok 4: Použitie v deploy.sh

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
