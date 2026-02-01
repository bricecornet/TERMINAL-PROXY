# TERMINAL-PROXY
FR : Proxy PHP ultra-léger qui rend le web accessible aux vieux ordis (C64, Psion) et zen sur mobile. Convertit tout site en mode Texte ou Terminal Matrix. Sans pub ni JS.  EN : Ultra-light PHP proxy making the web accessible to vintage gear (C64, Psion) and zen on mobile. Converts any site into Text-only or Matrix Terminal mode. Ad-free, no JS.

```text
   _____  ___________ __________  ____  ____  ____  ____ 
  / __/ \/ /_  __/ _ /_  __/ __ \/ __ \/ __ \/ __ \/ __ \
 / _/ / \/ // / / __/ / / / /_/ / /_/ / /_/ / /_/ / /_/ /
/___/_/\__/_\__/_/ /_/_/  \____/\____/ .___/\____/\____/ 
                                    /_/                  

```

> **Live Demo:** [https://telex.wasmer.app/](https://telex.wasmer.app/)

---

## 🇫🇷 Français

### À propos du projet

**Terminal Proxy** est une passerelle web (proxy) ultra-légère écrite en PHP, conçue pour rendre le web moderne accessible aux machines obsolètes, ou pour offrir une expérience de lecture "zen" et sans distraction sur les appareils récents.

Le projet nettoie le web en temps réel : il supprime les publicités, les traceurs, le JavaScript, les iframes et le CSS lourd pour ne garder que l'essentiel : le texte et la structure.

### Fonctionnalités Clés

#### 1. Routage Intelligent (`index.php`)

Le point d'entrée du site détecte automatiquement le `User-Agent` de votre appareil pour vous servir la version la plus adaptée :

* **Détection Retro :** Redirige automatiquement les vieux systèmes (Windows CE, Pocket PC, PalmOS, Psion, Nokia S60, MS-DOS, IE 1-7, Lynx, etc.) vers la version *Vintage*.
* **Détection Moderne :** Redirige les smartphones, tablettes et PC récents vers la version *Modern*.

#### 2. Mode Vintage (`vintage.php`)

Une version brute, optimisée pour les **connexions bas débit** et les **processeurs limités**.

* Aucun CSS, aucun JS.
* Compatible avec les navigateurs textuels (Lynx, W3M) et les anciens moteurs de rendu.
* Idéal pour : Commodore 64 (avec carte ethernet), Atari ST, Amiga, Psion 5mx, Nokia Communicator, Palm Pilot, Windows 95/98.

#### 3. Mode Moderne (`modern.php`)

Une interface "Hacker / Cyberpunk" pour les appareils actuels.

* Design style "Terminal" avec effets CRT (Scanlines, scintillement).
* **Entièrement Responsive :** S'adapte parfaitement aux mobiles (l'interface change dynamiquement pour rester ergonomique sur petit écran).
* Navigation au clavier ou tactile.

### Installation

Il suffit d'héberger les fichiers sur n'importe quel serveur web supportant **PHP** (avec extensions `cURL` et `DOM`).

1. Téléchargez le code.
2. Déposez `index.php`, `modern.php` et `vintage.php` à la racine.
3. C'est tout.

### Licence

Ce projet est open source sous licence **MIT**. Vous êtes libre de le modifier, de le distribuer et de l'utiliser pour vos projets personnels ou rétro-computing.

---

## 🇬🇧 English

### About the Project

**Terminal Proxy** is an ultra-lightweight PHP web gateway designed to make the modern web accessible on obsolete hardware, or to provide a distraction-free, "zen" reading experience on modern devices.

The project sanitizes the web in real-time: it strips out ads, trackers, JavaScript, iframes, and heavy CSS, keeping only the essentials: text and structure.

### Key Features

#### 1. Smart Dispatch (`index.php`)

The entry point automatically detects your device's `User-Agent` to serve the most appropriate version:

* **Retro Detection:** Automatically redirects legacy systems (Windows CE, Pocket PC, PalmOS, Psion, Nokia S60, MS-DOS, IE 1-7, Lynx, etc.) to the *Vintage* version.
* **Modern Detection:** Redirects modern smartphones, tablets, and PCs to the *Modern* version.

#### 2. Vintage Mode (`vintage.php`)

A raw version optimized for **low bandwidth** and **limited processors**.

* No CSS, no JS.
* Compatible with text-based browsers (Lynx, W3M) and ancient rendering engines.
* Perfect for: Commodore 64 (with ethernet), Atari ST, Amiga, Psion 5mx, Nokia Communicator, Palm Pilot, Windows 95/98.

#### 3. Modern Mode (`modern.php`)

A "Hacker / Cyberpunk" interface for current devices.

* "Terminal" style design with CRT effects (Scanlines, flicker, glow).
* **Fully Responsive:** Perfectly adapts to mobile devices (interface changes dynamically to remain ergonomic on small screens).
* Keyboard or touch navigation.

### Installation

Simply host the files on any web server supporting **PHP** (with `cURL` and `DOM` extensions enabled).

1. Download the code.
2. Upload `index.php`, `modern.php`, and `vintage.php` to your root directory.
3. Done.

### License

This project is open source under the **MIT License**. You are free to modify, distribute, and use it for your personal or retro-computing projects.
