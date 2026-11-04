CERT_NAME := caddy-root.crt
CERT_SRC  := caddy:/data/caddy/pki/authorities/local/root.crt
TMP_CERT  := /tmp/$(CERT_NAME)
HOSTS_FILE := /etc/hosts

.PHONY: setup install_cert install_host dev stop stop_dev sh cp_env app_key install_dependencies build_app db_shell db_reset

setup: cp_env
	$(MAKE) dev
	$(MAKE) install_cert
	$(MAKE) install_host
	$(MAKE) install_dependencies
	$(MAKE) build_app

cp_env:
	cp .env.example .env
	$(MAKE) app_key

# Génère une APP_KEY aléatoire dans le .env (clé des URL signées)
app_key:
	@key=$$(openssl rand -base64 32) && \
	sed -i.bak "s|^APP_KEY=.*|APP_KEY=\"$$key\"|" .env && rm -f .env.bak && \
	echo "APP_KEY générée dans .env"

build:
	docker compose exec app npm run build

stop:
	docker compose --profile dev down
	
dev:
	docker compose --profile dev up -d

# Tente d'installer le certificat sur le poste
install_cert:
	@echo "Attente du certificat Caddy..."
	@for i in $$(seq 1 30); do \
		docker compose cp $(CERT_SRC) $(TMP_CERT) 2>/dev/null && break; \
		sleep 1; \
	done; \
	[ -f $(TMP_CERT) ] || { echo "Certificat introuvable." >&2; exit 1; }
	@set -e; trap 'rm -f $(TMP_CERT)' EXIT; \
	if [ "$$(uname -s)" = "Darwin" ]; then \
		echo "macOS détecté"; \
		security add-trusted-cert -r trustRoot -k "$$HOME/Library/Keychains/login.keychain-db" $(TMP_CERT); \
	elif command -v update-ca-certificates >/dev/null 2>&1; then \
		echo "Debian/Ubuntu détecté"; \
		sudo cp $(TMP_CERT) /usr/local/share/ca-certificates/$(CERT_NAME); \
		sudo update-ca-certificates; \
	elif command -v trust >/dev/null 2>&1; then \
		echo "Arch/Fedora/RHEL détecté"; \
		sudo trust anchor --store $(TMP_CERT); \
	else \
		echo "Distribution non supportée : installez le certificat manuellement." >&2; \
		exit 1; \
	fi

# Ajoute le domaine du .env dans le fichier hosts s'il n'y est pas déjà
# L'entrée ::1 évite l'attente mDNS de 5 s sur les domaines .local sous macOS
install_host:
	@domain=$$(. ./.env && echo "$$PROXY_DOMAIN"); \
	[ -n "$$domain" ] || { echo "PROXY_DOMAIN introuvable dans .env." >&2; exit 1; }; \
	for ip in 127.0.0.1 ::1; do \
		if grep -Eq "^[[:space:]]*$$ip[[:space:]]([^#]*[[:space:]])?$$domain([[:space:]]|$$)" $(HOSTS_FILE); then \
			echo "$$ip $$domain déjà présent dans $(HOSTS_FILE)"; \
		else \
			echo "Ajout de $$ip $$domain dans $(HOSTS_FILE)"; \
			echo "$$ip $$domain" | sudo tee -a $(HOSTS_FILE) >/dev/null; \
		fi; \
	done

install_dependencies:
	docker compose exec app npm ci
	docker compose exec app composer i

build_app:
	docker compose exec app npm run build

sh:
	docker compose exec -it app /bin/sh

# Console MariaDB
db_shell:
	docker compose exec -it db sh -c 'mariadb -u"$$MARIADB_USER" -p"$$MARIADB_PASSWORD" "$$MARIADB_DATABASE"'

# Supprime la base et la recrée depuis database/schema.sql + seed.sql
db_reset:
	docker compose rm -sf db
	docker volume rm -f caddistan_db-data
	docker compose up -d --wait db

# Supprime aussi le fichier de HMR laissé lors de l'arrêt brutal de Vite
stop_dev:
	docker compose down vite
	rm -f src/public/hot 