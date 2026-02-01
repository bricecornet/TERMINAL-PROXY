<?php
/**
 * RETRO-WEB WASHER V2
 * Logic: H1 -> Body -> Link Lists -> Global Menu
 * Filter: Removes "Publicité" labels
 */

// Configuration
define('USER_AGENT', 'Mozilla/4.0 (compatible; MSIE 4.01; Windows 95)');
define('TIMEOUT', 15);

// Mots clés à supprimer (sensible à la casse via regex)
define('BAD_WORDS_REGEX', '/^\s*(Publicité|Sponsorisé|Advertisement|Annonces)\s*$/iu');

// --- FONCTIONS UTILITAIRES ---

function rel2abs($rel, $base) {
    if (empty($rel)) $rel = ".";
    if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;
    if ($rel[0] == '#' || $rel[0] == '?') return $base . $rel;
    extract(parse_url($base));
    /** @var string $scheme */ /** @var string $host */ /** @var string $path */
    $path = preg_replace('#/[^/]*$#', '', $path ?? '/');
    if ($rel[0] == '/') $path = '';
    $abs = "$host$path/$rel";
    $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
    for($n=1; $n>0; $abs=preg_replace($re, '/', $abs, -1, $n)) {}
    return $scheme . '://' . $abs;
}

// Fonction récursive pour nettoyer le HTML des termes interdits
function remove_ad_labels($node) {
    if ($node->nodeType === XML_TEXT_NODE) {
        // Si le noeud texte contient juste "Publicité", on le vide
        if (preg_match(BAD_WORDS_REGEX, $node->textContent)) {
            $node->textContent = "";
        }
    } elseif ($node->nodeType === XML_ELEMENT_NODE) {
        // Si un petit conteneur contient le mot interdit, on supprime le conteneur
        $text = trim($node->textContent);
        if (strlen($text) < 20 && preg_match(BAD_WORDS_REGEX, $text)) {
            $node->parentNode->removeChild($node);
            return;
        }
        // Sinon on descend dans les enfants
        if ($node->hasChildNodes()) {
            // On fait une copie de la liste des enfants car on va peut-être en supprimer
            $children = iterator_to_array($node->childNodes);
            foreach ($children as $child) {
                remove_ad_labels($child);
            }
        }
    }
}

// Fonction principale de rendu d'un noeud vers du HTML simple
function render_node($node, $final_url, $myself) {
    $output = "";
    
    if ($node->nodeType === XML_TEXT_NODE) {
        $text = trim($node->textContent);
        return !empty($text) ? $text . " " : "";
    }

    if ($node->nodeType === XML_ELEMENT_NODE) {
        $tag = strtolower($node->nodeName);
        $allowed_tags = ['h2', 'h3', 'h4', 'p', 'b', 'strong', 'i', 'em', 'blockquote', 'br', 'ul', 'ol', 'li', 'a']; // H1 est traité à part
        
        $child_content = "";
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $child_content .= render_node($child, $final_url, $myself);
            }
        }

        if (empty(trim($child_content)) && $tag !== 'br') return "";

        if ($tag == 'a') {
            $href = $node->getAttribute('href');
            if ($href && strpos($href, 'javascript') === false && substr($href, 0, 1) !== '#') {
                $abs_url = rel2abs($href, $final_url);
                $proxy_url = $myself . '?url=' . urlencode($abs_url);
                return "<a href=\"{$proxy_url}\">{$child_content}</a> ";
            }
            return $child_content . " ";
        }
        
        if (in_array($tag, ['h2', 'h3', 'h4'])) return "<$tag>" . $child_content . "</$tag>\n";
        if ($tag == 'p') return "<p>" . $child_content . "</p>\n";
        if ($tag == 'li') return "<li>" . $child_content . "</li>\n";
        // Note: UL/OL sont gérés ici pour les listes qui restent dans le BODY (listes à puces de texte)
        if ($tag == 'ul' || $tag == 'ol') return "<ul>\n" . $child_content . "</ul>\n";
        if ($tag == 'br') return "<br>\n";
        if ($tag == 'blockquote') return "<blockquote>" . $child_content . "</blockquote>\n";

        return $child_content . " "; // Divs, Spans, etc. deviennent invisibles
    }
    return "";
}

// --- LOGIQUE PRINCIPALE ---

$target_url = isset($_GET['url']) ? trim($_GET['url']) : '';

// Buckets de contenu
$bucket_title = "";
$bucket_body = "";
$bucket_lists = ""; // Listes de liens (sidebars, "a lire aussi")
$bucket_nav = "";   // Menus globaux (header, footer)

if ($target_url) {
    if (!filter_var($target_url, FILTER_VALIDATE_URL)) $target_url = 'http://' . $target_url;
    
    $my_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . strtok($_SERVER["REQUEST_URI"], '?');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $target_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, USER_AGENT);
    curl_setopt($ch, CURLOPT_TIMEOUT, TIMEOUT);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $html = curl_exec($ch);
    $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    if ($html) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        // 1. NETTOYAGE BRUT (Scripts, Styles, Pubs structurelles)
        $removals = $xpath->query("//script | //style | //noscript | //iframe | //svg | //img | //video | //canvas | //form | //meta | //link | //aside");
        foreach ($removals as $node) $node->parentNode->removeChild($node);
        
        // 2. SUPPRESSION DU MOT "PUBLICITÉ"
        remove_ad_labels($dom);

        // 3. EXTRACTION DU H1 (Le premier trouvé devient LE titre)
        $h1_node = $xpath->query("//h1")->item(0);
        if ($h1_node) {
            $bucket_title = "<h1>" . trim($h1_node->textContent) . "</h1>";
            $h1_node->parentNode->removeChild($h1_node); // On l'enlève du flux
        } else {
            // Fallback si pas de H1 : on prend le titre de la page
            $title_tag = $xpath->query("//title")->item(0);
            if ($title_tag) $bucket_title = "<h1>" . trim($title_tag->textContent) . "</h1>";
        }

        // 4. IDENTIFICATION ET DÉPLACEMENT DES MENUS GLOBAUX
        $nav_nodes = $xpath->query("//nav | //header | //footer");
        foreach ($nav_nodes as $node) {
            // On traite le contenu du menu tout de suite pour le stocker
            $bucket_nav .= render_node($node, $final_url, $my_url);
            $node->parentNode->removeChild($node); // On l'enlève du flux
        }

        // 5. IDENTIFICATION DES LISTES DE LIENS (Sidebars, Related posts...)
        // Logique : Une liste est une "liste de liens" si elle contient beaucoup de liens <a> par rapport au texte brut.
        $lists = $xpath->query("//ul | //ol");
        foreach ($lists as $node) {
            $links_count = $node->getElementsByTagName('a')->length;
            $items_count = $node->getElementsByTagName('li')->length;
            
            // Si c'est une liste avec des items, et que plus de 50% des items contiennent des liens
            // OU s'il y a beaucoup de liens (>3), on considère que c'est de la navigation secondaire.
            if ($items_count > 0 && ($links_count / $items_count > 0.5 || $links_count > 5)) {
                $bucket_lists .= "<ul>" . render_node($node, $final_url, $my_url) . "</ul>";
                $node->parentNode->removeChild($node); // On l'enlève du flux principal (BODY)
            }
        }

        // 6. RESTE LE "CORPS" (BODY)
        // On prend le body (ou ce qu'il en reste)
        $body_node = $dom->getElementsByTagName('body')->item(0);
        if (!$body_node) $body_node = $dom;
        
        $bucket_body = render_node($body_node, $final_url, $my_url);

    } else {
        $bucket_body = "<p>Erreur: Impossible de charger le site.</p>";
    }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 3.2 Final//EN">
<html>
<head>
    <title>TERMINAL-PROXY_</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
</head>
<body bgcolor="#FFFFFF" text="#000000" link="#0000FF" vlink="#800080" alink="#FF0000">

    <b>[ &nbsp; </b><a href="?"><b>VISIT ANOTHER WEBSITE</b></a><b> &nbsp; ]</b>
    <hr size="1" noshade>

    <?php if ($target_url): ?>
        
        <?php echo $bucket_title; ?>
        <hr size="1" width="50%" align="left">

        <?php echo $bucket_body; ?>
		
		<?php if (!empty($bucket_nav)): ?>
            <br>
            <hr size="1" noshade>
            <h3>SITE NAVIGATION :</h3>
            <ul>
                <?php echo $bucket_nav; ?> </ul>
        <?php endif; ?>
		
		<?php if (!empty($bucket_lists)): ?>
            <br>
            <hr size="1" noshade>
            <h3>RELATED LINKS :</h3>
            <?php echo $bucket_lists; ?>
        <?php endif; ?>

    <?php else: ?>
        <center>
        <h1>TERMINAL-PROXY_</h1>
        <p><i>> AWAITING TARGET URL...</i></p>
        <form action="" method="GET">
            URL: <input type="text" name="url" size="40"><br><br>
            <input type="submit" value="LOAD PAGE">
        </form>
		<br><br>
		| <a href="modern.php">Available for SMARTPHONE and PC</a> |
		<br><br>
        </center>
       <?php endif; ?>
	 <hr size="1" noshade>  
	<b>[ &nbsp; </b><a href="?"><b>VISIT ANOTHER WEBSITE</b></a><b> &nbsp; ]</b> --- <b>[ &nbsp; </b><a href="#top"><b>TOP</b></a><b> &nbsp; ]</b>
    <hr size="1" noshade>
<p><small>TERMINAL-PROXY_ | Created by <a href="https://www.ihaveto.be">Brice Cornet</a> | Sponsored by <a href="https://simple-crm.ai">Simple CRM .ai</a> | <a href="https://github.com/bricecornet/TERMINAL-PROXY/">GITHUB</a></small></p>
</body>
</html>