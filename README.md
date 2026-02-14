# Juuksuri broneerimissüsteem (MVP)
https://barb-booking.onrender.com
- URL: /admin
- User: admin
- Pass: admin


## Kirjeldus
Tegemist on veebipõhise juuksuri broneerimissüsteemiga, mis võimaldab kasutajal
valida juuksuri, kuupäeva ja vaba aja ning luua broneeringu.
Rakendus on loodud MVC-laadse arhitektuuriga ning kasutab Slim Frameworki ja Twig templating engine’it.

---

## Tehnoloogiad
- PHP
- Slim Framework
- Twig
- HTML / CSS
- Docker (arenduskeskkond)

---

## Funktsionaalsus

### Avalik vaade
- Kasutaja saab valida juuksuri ja kuupäeva
- Süsteem kuvab ainult vabad ajad
- Broneeritud ajad ei ole valitavad
- Broneering kinnitatakse serveripoolselt

### Admin-vaade
- Eraldi admin-vaade broneeringute loendi kuvamiseks
- Admin-vaade on kaitstud ligipääsukontrolliga (HTTP Basic Auth)

---

## Arhitektuur

- **Controller**: Slim route’id (`public/index.php`)
- **View**: Twig mallid (`templates/`)
- **Model (MVP)**: PHP andmestruktuurid (broneeringud, juuksurid)

Frontend ja backend on loogiliselt eristatud:
- Frontend kuvab andmeid
- Backend valideerib, töötleb ja otsustab

---

## Äriloogika
- Vabad ajad genereeritakse serveris (09:00–17:00, 30 min intervall)
- Enne broneeringu lisamist kontrollitakse kattuvusi
- Topeltbroneering samale ajale ei ole võimalik
- Otsused tehakse andmete põhjal (vaba vs hõivatud aeg)

---

## Turvalisus
- Kasutaja sisendid valideeritakse
- Admin-vaade on kaitstud volitamata ligipääsu eest
- Broneeringu loogika on serveripoolne (ei sõltu kasutaja HTML-ist)

---

## Käivitamine

```bash
docker compose up
