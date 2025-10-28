Een webapplicatie gebouwd in PHP van een funko website 
📋 Functionaliteiten

Gebruikersbeheer: Registreer een account met alleen een gebruikersnaam en leeftijd
Uniek inlogsysteem: Eenvoudige authenticatie op basis van naam en leeftijd
Profielbeheer:

Wijzig je accountnaam
Verwijder je account volledig
database exsporteren
verwerken naar pdf



Data export:

Download gegevens in Excel-formaat
Genereer PDF-rapporten
Volledige database backup mogelijk



⚙️ Technische vereisten

PHP versie: 8.1.0 of hoger
Dependency management: Composer

🚀 Installatie-instructies
Stap 1: Project downloaden
bashgit clone https://github.com/okwastaken/gegevenssprint
cd gegevenssprint
Stap 2: Dependencies installeren
Open een terminal/command prompt in de projectmap en voer uit:
bashcomposer install
Stap 3: Configuratie instellen
Maak een configuratiebestand aan:
bashcp .env.example .env
🗄️ Database configuratie
Database aanmaken

Ga naar phpMyAdmin: http://localhost/phpmyadmin
Klik op Nieuwe database
Geef een naam op (bijvoorbeeld: example_db)

Alternatief: Importeer het meegeleverde backup-bestand uit de backup folder
Database credentials instellen
Bewerk het .env bestand met jouw MAMP/server gegevens:
dotenvDB_HOST=example_host
DB_USER=example_user
DB_PASS=example_pass
DB_NAME=example_db

De applicatie is nu klaar voor gebruik! 🎉
