# Classroom Manager

Početna faza aplikacije za upravljanje učionicama. Trenutno su implementirani registracija, prijava, profil, odjava, promjena lozinke te administratorsko upravljanje korisnicima.

## Lokalno pokretanje (XAMPP)

1. Pokrenite Apache i MySQL u XAMPP-u.
2. U phpMyAdminu izradite bazu `classroommanager` i uvezite `classroommanager.sql`.
3. Otvorite `http://localhost/ClassRoomManager/`.

Lokalna konfiguracija prema zadanim postavkama koristi `127.0.0.1`, bazu `classroommanager`, korisnika `root` i praznu lozinku. Ako su podaci za MySQL drukčiji, postavite varijable okruženja `DB_HOST`, `DB_NAME`, `DB_USER` i `DB_PASS` prije pokretanja Apachea. Lozinke korisnika spremaju se kao hash.

Zahtijeva PHP 8.1 ili noviji s PDO MySQL ekstenzijom te MySQL/MariaDB.

## Početni administrator

Registracija uvijek stvara račun s ulogom `Student`. Nakon registracije prvog računa, dodijelite mu administratorsku ulogu u phpMyAdminu:

```sql
UPDATE users SET Role = 'Admin' WHERE Email = 'vas-email@example.com';
```

Odjavite se i ponovno prijavite. Poveznica **Admin panel** tada će biti dostupna u navigaciji.