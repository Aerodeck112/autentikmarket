# RESEARCH — Autentik Market

> Faza 1. Research realizat online la 14.10.2026, înainte de scrierea codului.
> Scop: să înțelegem ce reprezintă proiectul înainte de a construi platforma.
> **Regulă:** nu am inventat date juridice, fiscale, prețuri sau identități de firme. Tot ce nu s-a putut verifica apare ca *placeholder* în aplicație.

## 1. Surse consultate

| Sursă | Ce am aflat |
|---|---|
| autentikmarket.ro (site-ul actual) | WordPress, temă implicită *Twenty Twenty-Five*. Titlu: „Târgul lunar de producători și artizani Autentik Market”. Tagline bilingv: „Târg lunar de producători și artizani · Havi termelői és kézműves vásár”. Meniuri rămase din tema implicită (Blog, About, FAQs, Authors, Patterns, Themes): site-ul actual nu este o platformă, ci o pagină de prezentare minimală. |
| visitmures.com (RO / HU / EN) | Mai multe ediții listate: 11.10.2025, 14.02.2026, 07.03.2026, 13.06.2026, 11.07.2026. Locație: **Merkur, Str. Livezeni nr. 7, Târgu Mureș** (HU: *Merkúr, Jeddi út 7*). Descrierea oficială este disponibilă în RO și HU. |
| zi-de-zi.ro (presă locală, 08.06.2026) | „Târgul Autentik Market revine, sâmbătă, 13 iunie, în curtea magazinului Merkur din cartierul Tudor”. Program raportat: **08:00 – 15:00**, sâmbăta. |
| romania-atractiva.ro | Ediția din 14.02.2026, între 09:00 și 15:00. |
| zilesinopti.ro | „Târg lunar de producători și artizani \| Autentik Market @ Strada Livezeni”, cu link spre pagina de Facebook. |
| facebook.com/AutentikMarket | Pagina oficială există, dar conținutul nu este accesibil fără autentificare. Evenimentele sunt publicate ca Facebook Events (linkuri fb.me de pe Visit Mureș). |
| Instagram, YouTube | Linkuri prezente pe site-ul actual. Conținutul nu a putut fi analizat automat. |
| Reghin | Nu am găsit public o ediție Autentik Market la Reghin. La Reghin, primăria organizează separat „Târgul Producătorilor Locali” (Parcul Central). Reghin este tratat în aplicație ca **locație configurabilă**, pregătită pentru schița pe care o va furniza clientul. |
| Autentik Shop | Nu am găsit public un magazin cu acest nume. Conform brief-ului, există separat. În aplicație apare **doar ca URL extern configurabil** (Setări → Integrări). |

## 2. Ce este Autentik Market (sinteză)

**Autentik Market este un târg lunar, organizat sâmbăta în Târgu Mureș, care aduce împreună producători locali, artizani și consumatori conștienți.**

Mesajele-cheie din comunicarea existentă (parafrazate din RO/HU):

- târgul „apropie producătorii, artizanii și consumatorii conștienți”, într-un **spațiu comunitar**;
- producătorii locali cresc și prepară, în măsura posibilităților, **alimente esențiale, curate, fără chimicale**;
- artizanii realizează **obiecte utile de zi cu zi, produse de curățenie, igienă și îngrijire personală din ingrediente naturale**, „de la materie primă la produs finit”;
- târgul contribuie la **sustenabilitate**, la accesul oamenilor la **produse autohtone**, la **sănătate**, la **protecția mediului** și la **susținerea micilor antreprenori**;
- formularea „**este despre oameni și pentru oameni**” (*for people and about people*);
- versiunea HU vorbește despre o **comunitate care se poate susține singură** (*önfenntartó közösség*).

Principiul care diferențiază Autentik Market de alte târguri: **producători reali, nu revânzători**. Acest principiu a determinat direct un modul al platformei: **workflow-ul de verificare a producătorilor** (documente, DSVSA, certificări, fotografii, istoric, notă internă).

## 3. Categorii de produse identificate

Din descrierile publice:

- alimente naturale / sănătoase, preparate locale;
- miere și produse apicole;
- sucuri naturale;
- produse de panificație și preparate tradiționale (mențiuni generice);
- obiecte artizanale, obiecte utile de zi cu zi;
- produse de curățenie din ingrediente naturale;
- produse de igienă și îngrijire personală (cosmetice naturale).

Seed-ul aplicației conține categorii editabile: *Miere & produse apicole; Lactate & brânzeturi; Legume & fructe; Panificație & patiserie; Conserve, dulcețuri & zacuscă; Sucuri & siropuri naturale; Carne & preparate; Ceaiuri & plante; Cosmetice naturale; Produse de curățenie naturale; Artizanat & meșteșuguri; Textile & ceramică*. Toate au denumiri RO și HU și pot fi modificate din CRM.

## 4. Public și limbă

- Târgu Mureș / Marosvásárhely este un oraș bilingv. Publicul este **mixt, român și maghiar**, iar comunicarea oficială (tagline, Visit Mureș) este deja **RO + HU**.
- Concluzie: limba nu este doar o traducere a site-ului. Este **o proprietate a relației cu fiecare persoană** (`preferred_language`), cu impact direct asupra comunicărilor automate și asupra statisticilor (RO/HU per eveniment).

## 5. Identitate vizuală — audit

- Site-ul actual nu are un sistem vizual definit: temă WordPress implicită, logo afișat ca imagine (alt text: „targul micilor fermieri si producatori”) și o grafică de eveniment.
- **Identitatea oficială a fost furnizată de proprietar** (sigla orizontală „Autentik Market by My Family Center” și sigla rotundă). Culorile au fost extrase din pixelii siglei:

| Culoare oficială | Hex | Token |
|---|---|---|
| verde închis | `#1D5733` | `--color-forest` (principală, text pe titluri, butoane primare) |
| portocaliu soare | `#DD8D2B` | `--color-sun` (CTA accent, cu text verde închis) |
| grâu | `#F0DBA2` | `--color-wheat` (suprafețe calde, selecție, „în curs”) |
| verde crud | `#75BD50` | `--color-leaf` (doar suprafețe/grafică, niciodată text mic) |

- Derivate pentru accesibilitate (WCAG AA), toate în `resources/css/app.css`:
  - `--color-forest-dark` `#133E23` pentru fundaluri închise;
  - `--color-clay` `#A35F0C`, portocaliu închis pentru linkuri și text accent (4,9:1 pe crem);
  - `--color-cream` `#FAF3E1` / `--color-paper` `#FFFDF7` pentru fundaluri;
  - `--color-ink` `#1A2B20` pentru text.
- Reguli de contrast:
  - textul alb pe portocaliul oficial nu trece (2,65:1), așa că butonul accent are fundal soare și text verde închis (4,55:1);
  - portocaliul pur se folosește ca text doar pe verde închis.
- Fișierele siglei sunt în `public/images/brand/` (PNG + WebP; variantele `-light` au verdele recolorat în crem, pentru fundaluri închise):
  - `logo-wordmark` pentru header;
  - `logo-horizontal` pentru footer, cu „by My Family Center”;
  - `logo-stacked` pentru hero;
  - `emblem` pentru favicon și iconurile PWA.
- Componenta `<x-logo>` folosește aceste fișiere. Un logo încărcat în **CRM → Setări → Identitate** (`brand.logo_path`) are prioritate.

### Direcție vizuală

- Tipografie:
  - **Oswald**, un condensed bold apropiat de literele înalte din siglă, pentru titluri;
  - **Inter** pentru UI, lizibil pe mobil.
- Fonturile sunt self-hosted prin `@fontsource` și împachetate în build, fără request-uri către Google Fonts (motive GDPR).
- Fără „frunze peste tot”. Vedetele vizuale sunt **fotografiile reale** ale producătorilor, oamenilor și produselor, încărcate din CRM (hero, galerie, profiluri). Unde nu există încă fotografii, layout-ul folosește blocuri tipografice și culoare, nu imagini stoc.

## 6. Ton de comunicare

- Cald, direct, comunitar: „vino să-i cunoști pe cei care îți fac mâncarea”.
- Fără limbaj de marketplace („cele mai mici prețuri”, „ofertă”).
- Accent pe **oameni** (producătorul cu nume și poveste), **autentic**, **local**, **calitate**.
- În maghiară, același ton (tegeződés, adică adresare cu „tu”, ca în comunicarea existentă).

## 7. Date confirmate vs. placeholder

| Informație | Status în aplicație |
|---|---|
| Nume proiect, concept, tagline RO/HU | folosite |
| Locație Târgu Mureș: Merkur, Str. Livezeni 7 | seed demo (editabil) |
| Program 08:00–15:00 sâmbăta | seed demo (editabil per eveniment) |
| Reghin: locație și adresă | **placeholder** („de confirmat”) |
| Tarife standuri | **neinventate**: câmp gol / „tarif la cerere” până la configurare |
| Date juridice organizator (firmă, CUI, adresă) | **placeholder** în Setări → Organizator și în template-ul de contract |
| Text juridic contract / anexă | **schelet cu placeholder-e**, de completat de jurist |
| Termeni, confidențialitate, GDPR | **schelet**, de validat juridic |
| Schițe Târgu Mureș / Reghin | **primite și digitizate** în versiunea 1.1.0 (vezi §8). Scara este aproximativă (stand = 3 × 3 m). |

## 8. Schițele târgurilor — status

Organizatorul a trimis cele două schițe (A4), digitizate în versiunea 1.1.0:

- **Târgu Mureș** — curtea/parcarea magazinului Merkur:
  - 20 de standuri; standul 3 = INFO;
  - repere: râul, zonele verzi, aleea pietonală, clădirea Merkur, WC.
- **Reghin** — piața din fața Catedralei Greco-Catolice, lângă strada Aurel Vlaicu:
  - 13 standuri; standul 2 = INFO;
  - repere: statuia Cardinal Alexandru Todea, fântâna arteziană, zonele verzi, accesul auto posibil.

Ce se poate verifica sau completa ulterior:
- scara reală: schițele nu au cotă, așa că am presupus standuri de 3 × 3 m;
- tarifele și electricitatea pe stand;
- adresa exactă de la Reghin. Am completat-o din schiță: „Piața Catedralei Greco-Catolice (str. Aurel Vlaicu)”.

Detalii tehnice: `ARCHITECTURE.md` § 7.

## 9. Concluzii pentru produs

1. **Relația centrală: PRODUCĂTOR ↔ AUTENTIK MARKET ↔ VIZITATOR.** Fiecare modul alimentează această relație.
2. **Verificarea producătorilor** este cheia credibilității („fără revânzători”).
3. **Bilingvismul** este nativ: în URL-uri, în CRM, în template-uri și în statistici.
4. **Recurența lunară** justifică fidelizarea (card, vizite, praguri) și tombolele.
5. **Evenimentele sunt într-o curte/parcare cu standuri**, de unde hărțile digitale și rezervarea vizuală.
6. **Autentik Shop rămâne extern**: aici apar doar linkuri configurabile.

## Surse

- https://autentikmarket.ro/
- https://visitmures.com/evenimente/autentik-market456411 (RO), https://visitmures.com/hu/esemenyek/autentik-market456411 (HU)
- https://visitmures.com/en/events/autentik-market45641121, https://visitmures.com/en/events/autentik-market123
- https://www.zi-de-zi.ro/2026/06/08/targul-autentik-market-revine-sambata-13-iunie-in-curtea-magazinului-merkur-din-cartierul-tudor/
- https://romania-atractiva.ro/ro/events/t-rg-de-produc-tori--i-artizani-autentik-market
- https://zilesinopti.ro/evenimente/autentik-market-strada-livezeni/
- https://www.facebook.com/AutentikMarket/
- https://reghin.ro/evenimente-reghin/
