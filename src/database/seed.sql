-- Données de démonstration
-- Comptes : admin@caddistan.local / Admin123!   client@caddistan.local / Client123!
SET NAMES utf8mb4;

INSERT INTO users (email, password, first_name, last_name, role) VALUES
('admin@caddistan.local',  'Admin123!', 'Alice', 'Admin', 'admin'),
('client@caddistan.local', 'Client123!', 'Claude', 'Client', 'customer');

INSERT INTO categories (id, name, slug, description) VALUES
(1, 'Supermarché',           'supermarche', 'Chariots de magasin robustes, du 60 au 240 litres.'),
(2, 'Golf',                  'golf',        'Chariots de golf manuels et électriques pour porter votre sac.'),
(3, 'Logistique & entrepôt', 'logistique',  'Chariots de manutention, diables et rolls pour professionnels.'),
(4, 'Courses & marché',      'marche',      'Poussettes de marché et cabas à roulettes pour le quotidien.'),
(5, 'Jardin & bricolage',    'jardin',      'Brouettes, chariots de jardin et dessertes d''atelier.');

INSERT INTO products (category_id, name, slug, short_description, description, price_cents, stock, image, datasheet) VALUES
(1, 'Caddy Classic 100 L', 'caddy-classic-100l', 'Le chariot de supermarché par excellence.', 'Panier galvanisé de 100 litres, 4 roues pivotantes, siège enfant rabattable et serrure à jeton. Le standard indémodable des grandes surfaces.', 18900, 42, '/images/products/supermarche.svg', 'caddy-classic-100l.txt'),
(1, 'Caddy Maxi 240 L', 'caddy-maxi-240l', 'Pour les courses du mois en une seule fois.', 'Châssis renforcé, panier de 240 litres et plateau inférieur pour les packs d''eau. Idéal pour les hypermarchés et les familles nombreuses.', 27900, 15, '/images/products/supermarche.svg', NULL),
(1, 'Caddy Mini 60 L', 'caddy-mini-60l', 'Compact, maniable, parfait pour les supérettes.', 'Chariot en polypropylène recyclé de 60 litres, roues silencieuses. Se range facilement par emboîtement.', 12900, 60, '/images/products/supermarche.svg', NULL),
(2, 'Golf Trolley Pro 3 roues', 'golf-trolley-pro-3-roues', 'Chariot de golf pliable en un geste.', 'Châssis aluminium, frein à main, porte-carte de score et porte-parapluie. Se plie en moins de 3 secondes.', 22900, 20, '/images/products/golf.svg', 'golf-trolley-pro.txt'),
(2, 'Golf Trolley Électrique E-Caddie', 'golf-trolley-electrique-e-caddie', 'Autonomie de 36 trous, télécommande incluse.', 'Moteur 250 W, batterie lithium amovible, mode suivi et télécommande. Pour ne plus jamais pousser votre sac.', 89900, 6, '/images/products/golf.svg', NULL),
(2, 'Golf Trolley Classic 2 roues', 'golf-trolley-classic-2-roues', 'Le chariot à tirer, léger et fiable.', 'Structure acier, roues pneumatiques et sangles réglables. Le compagnon des parcours tranquilles.', 7900, 35, '/images/products/golf.svg', NULL),
(3, 'Diable Pro 250 kg', 'diable-pro-250kg', 'Diable acier pour charges lourdes.', 'Bavette acier, roues increvables et poignée ergonomique. Charge maximale 250 kg.', 9900, 28, '/images/products/logistique.svg', NULL),
(3, 'Chariot plateau 500 kg', 'chariot-plateau-500kg', 'Plateau antidérapant, dossier rabattable.', 'Plateau 120 x 80 cm, 2 roues fixes et 2 pivotantes avec frein. Charge maximale 500 kg.', 24900, 12, '/images/products/logistique.svg', 'chariot-plateau-500kg.txt'),
(3, 'Roll conteneur 4 faces', 'roll-conteneur-4-faces', 'Le roll de la grande distribution.', 'Roll grillagé 4 faces, démontable, idéal pour la préparation de commandes et le réassort.', 31900, 9, '/images/products/logistique.svg', NULL),
(4, 'Poussette de marché Mamie Turbo', 'poussette-marche-mamie-turbo', 'Six roues pour monter les escaliers.', 'Sac isotherme amovible de 50 litres, système 6 roues pour les marches, poignée réglable. Un classique revisité.', 5900, 50, '/images/products/marche.svg', NULL),
(4, 'Cabas à roulettes Citadin', 'cabas-roulettes-citadin', 'Léger, pliable, coloris au choix.', 'Cabas de 35 litres en polyester recyclé, se plie à plat. Parfait pour le marché du dimanche.', 3490, 80, '/images/products/marche.svg', NULL),
(5, 'Chariot de jardin benne basculante', 'chariot-jardin-benne-basculante', 'Benne de 300 L, se vide sans effort.', 'Benne polyéthylène basculante, roues pneumatiques et timon attelable à une tondeuse autoportée.', 15900, 18, '/images/products/jardin.svg', NULL),
(5, 'Desserte d''atelier 3 plateaux', 'desserte-atelier-3-plateaux', 'Tous vos outils à portée de main.', 'Trois plateaux à rebords, roulettes avec frein, charge 150 kg. Le meilleur ami du bricoleur.', 8900, 25, '/images/products/jardin.svg', NULL);

INSERT INTO reviews (product_id, user_id, rating, comment) VALUES
(1, 2, 5, 'Roule parfaitement droit, contrairement à celui de mon supermarché.'),
(4, 2, 4, 'Très pratique, le pliage est vraiment rapide.'),
(10, 2, 5, 'Ma grand-mère monte enfin ses trois étages sans souci !');
