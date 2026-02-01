<?php
// =====================================================================
// MOTEUR PHP V2 (IDENTIQUE A LA VERSION PRECEDENTE)
// =====================================================================

define('USER_AGENT', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'); 
define('TIMEOUT', 15);
define('BAD_WORDS_REGEX', '/^\s*(Publicité|Sponsorisé|Advertisement|Annonces|PUB)\s*$/iu');

function rel2abs($rel, $base) {
    if (empty($rel)) $rel = ".";
    if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;
    if ($rel[0] == '#' || $rel[0] == '?') return $base . $rel;
    extract(parse_url($base));
    $path = preg_replace('#/[^/]*$#', '', $path ?? '/');
    if ($rel[0] == '/') $path = '';
    $abs = "$host$path/$rel";
    $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
    for($n=1; $n>0; $abs=preg_replace($re, '/', $abs, -1, $n)) {}
    return $scheme . '://' . $abs;
}

function remove_ad_labels($node) {
    if ($node->nodeType === XML_TEXT_NODE) {
        if (preg_match(BAD_WORDS_REGEX, $node->textContent)) {
            $node->textContent = "";
        }
    } elseif ($node->nodeType === XML_ELEMENT_NODE) {
        $directText = "";
        foreach($node->childNodes as $child) {
             if ($child->nodeType === XML_TEXT_NODE) $directText .= $child->textContent;
        }
        if (preg_match(BAD_WORDS_REGEX, $directText) && strlen(trim($node->textContent)) < 30) {
             $node->parentNode->removeChild($node);
             return;
        }
      
        if ($node->hasChildNodes()) {
            $children = iterator_to_array($node->childNodes);
            foreach ($children as $child) remove_ad_labels($child);
        }
    }
}

function render_node($node, $final_url, $myself) {
    $output = "";
    if ($node->nodeType === XML_TEXT_NODE) {
        $text = trim($node->textContent);
        return !empty($text) ? htmlspecialchars($text) . " " : ""; 
    }
    if ($node->nodeType === XML_ELEMENT_NODE) {
        $tag = strtolower($node->nodeName);
        $child_content = "";
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) $child_content .= render_node($child, $final_url, $myself);
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
        if ($tag == 'li') return "<li><span>" . $child_content . "</span></li>\n"; 
        if ($tag == 'ul' || $tag == 'ol') return "<ul>\n" . $child_content . "</ul>\n";
        if ($tag == 'br') return "<br>\n";
        if ($tag == 'blockquote') return "<blockquote>" . $child_content . "</blockquote>\n";
        if (in_array($tag, ['b', 'strong'])) return "<strong>" . $child_content . "</strong> ";
        if (in_array($tag, ['i', 'em'])) return "<em>" . $child_content . "</em> ";
        return $child_content . " ";
    }
    return "";
}

$target_url = isset($_GET['url']) ? trim($_GET['url']) : '';
$bucket_title = ""; $bucket_body = ""; $bucket_lists = ""; $bucket_nav = "";
$load_time = 0;

if ($target_url) {
    $start_time = microtime(true);
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
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $removals = $xpath->query("//script|//style|//noscript|//iframe|//svg|//img|//video|//canvas|//form|//meta|//link|//aside|//figure");
        foreach ($removals as $node) $node->parentNode->removeChild($node);
        remove_ad_labels($dom);

        $h1_node = $xpath->query("//h1")->item(0);
        if ($h1_node) {
            $bucket_title = "<h1>" . trim($h1_node->textContent) . "</h1>";
            $h1_node->parentNode->removeChild($h1_node);
        } else {
            $title_tag = $xpath->query("//title")->item(0);
            if ($title_tag) $bucket_title = "<h1>" . trim($title_tag->textContent) . "</h1>";
        }

        $nav_nodes = $xpath->query("//nav|//header|//footer");
        foreach ($nav_nodes as $node) {
            $bucket_nav .= render_node($node, $final_url, $my_url);
            $node->parentNode->removeChild($node);
        }

        $lists = $xpath->query("//ul|//ol");
        foreach ($lists as $node) {
            $links_count = $node->getElementsByTagName('a')->length;
            $items_count = $node->getElementsByTagName('li')->length;
            if ($items_count > 0 && ($links_count / $items_count > 0.5 || $links_count > 5)) {
                $bucket_lists .= "<ul>" . render_node($node, $final_url, $my_url) . "</ul>";
                $node->parentNode->removeChild($node);
            }
        }
        $body_node = $dom->getElementsByTagName('body')->item(0);
        if (!$body_node) $body_node = $dom;
        $bucket_body = render_node($body_node, $final_url, $my_url);
    } else {
        $bucket_body = "<p class='error'>[ERROR] CONNECTION_RESET_BY_PEER. Target unreachable.</p>";
    }
    $load_time = round(microtime(true) - $start_time, 4);
}
?>
<!DOCTYPE html>
<html lang="fr" style="scroll-behavior: auto;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TERMINAL PROXY_</title>
    <style>
        :root {
            --bg-color: #000000;
            --text-color: #00FF00;
            --link-color: #ADFF2F;
            --alert-color: #FF3300;
            --font-stack: 'Courier New', Courier, 'Lucida Sans Typewriter', 'Lucida Console', monospace;
        }

        /* CORRECTIF BUG SCROLL : On s'assure que l'input autofocus ne force pas le scroll visuel */
        .terminal-input:focus { outline: none; }

        body::after {
            content: "";
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: repeating-linear-gradient(0deg, rgba(0,0,0,0.15) 0px, rgba(0,0,0,0.15) 1px, transparent 1px, transparent 3px);
            pointer-events: none; z-index: 999;
        }

        @keyframes turn-on {
            0% { transform: scale(1, 0.8) translate3d(0, 0, 0); filter: brightness(30) opacity(0); }
            30% { transform: scale(1, 0.8) translate3d(0, 100%, 0); filter: brightness(30) opacity(1); }
            50% { transform: scale(1.05, 0.9) translate3d(0, 0, 0); filter: brightness(1) opacity(1); }
            100% { transform: scale(1, 1) translate3d(0, 0, 0); filter: brightness(1) opacity(1); }
        }

        @keyframes flicker {
            0% { opacity: 0.9; } 50% { opacity: 1; } 100% { opacity: 0.98; }
        }

        body {
            margin: 0; padding: 20px;
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: var(--font-stack);
            font-size: 16px;
            line-height: 1.4;
            text-shadow: 0 0 4px rgba(0, 255, 0, 0.5);
            overflow-x: hidden;
            animation: turn-on 0.4s ease-out forwards;
        }

        #terminal-container {
            max-width: 800px;
            margin: 0 auto;
            animation: flicker 0.1s infinite;
        }

        h1, h2, h3, strong {
            text-transform: uppercase; font-weight: bold; letter-spacing: 1px; color: #FFF;
            text-shadow: 0 0 8px var(--text-color);
        }
        h1 { border-bottom: 2px dashed var(--text-color); padding-bottom: 10px; margin-bottom: 30px; }
        
        a {
            color: var(--link-color); text-decoration: none;
            border-bottom: 1px dotted var(--link-color); transition: all 0.2s;
        }
        a:hover, a:focus {
            background-color: var(--link-color); color: var(--bg-color);
            text-shadow: none; outline: none;
        }
        
        ul { list-style: none; padding-left: 20px; }
        li::before { content: "> "; color: var(--text-color); font-weight: bold; }
        li { margin-bottom: 8px; }

        blockquote {
            border-left: 4px solid var(--text-color); margin: 20px 0; padding-left: 20px;
            font-style: italic; opacity: 0.8;
        }

        hr { border: none; border-top: 1px dashed var(--text-color); opacity: 0.5; margin: 30px 0; }

        .sys-info { font-size: 0.8rem; opacity: 0.7; margin-bottom: 20px; border: 1px solid var(--text-color); padding: 5px; display: inline-block; }
        .blink { animation: blinking 1s step-end infinite; }
        @keyframes blinking { 0% { opacity: 1; } 50% { opacity: 0; } }

        .terminal-input-group { display: flex; margin: 30px 0; border: 2px solid var(--text-color); padding: 5px; }
        .prompt-label { padding: 10px; font-weight: bold; user-select: none; }
        .terminal-input {
            flex-grow: 1; background: transparent; border: none; color: #FFF;
            font-family: var(--font-stack); font-size: 16px; outline: none; padding: 10px;
        }
        .terminal-submit {
            background: var(--text-color); color: var(--bg-color); border: none;
            padding: 10px 20px; font-family: var(--font-stack); font-weight: bold; cursor: pointer;
        }
        .terminal-submit:hover { background: #FFF; }

        .section-header { background: var(--text-color); color: #000; padding: 5px 10px; display: inline-block; font-weight: bold; margin-top: 20px; text-shadow: none;}
        .error { color: var(--alert-color); text-shadow: 0 0 5px var(--alert-color); }
    </style>
</head>
<body id="top">
<div id="terminal-container">
    
    <div class="sys-info">
        SYSTEM READY. <span class="blink">_</span>
        <?php if($load_time > 0) echo "[DATA RECEIVED IN {$load_time}s]"; ?>
    </div>

    <?php if (!$target_url): ?>
        <br>
        <pre>
   _____  ___________ __________  ____  ____  ____  ____  ____  ____  ____ 
  / __/ \/ /_  __/ _ /_  __/ __ \/ __ \/ __ \/ __ \/ __ \/ __ \/ __ \/ __ \
 / _/ / \/ // / / __/ / / / /_/ / /_/ / /_/ / /_/ / /_/ / /_/ / /_/ / /_/ /
/___/_/\__/_\__/_/ /_/_/  \____/\____/ .___/\____/\____/\____/\____/\____/ 
                                    /_/                                    
        </pre>
        <p>> GATEWAY INITIALIZED.</p>
        <p>> MODE: TEXT_ONLY / NO_ADS / LOW_BANDWIDTH.</p>
        <p>> AWAITING TARGET URL...</p>
    <?php else: ?>
        <div><a href="?">&lt;&lt; RETURN TO CONSOLE</a></div>
        <hr>
    <?php endif; ?>

    <form action="" method="GET">
        <div class="terminal-input-group">
            <span class="prompt-label">C:\WEB\></span>
            <input type="text" id="url-input" name="url" class="terminal-input" value="<?php echo htmlspecialchars($target_url); ?>" placeholder="ENTER URL..." autocomplete="off">
            <button type="submit" class="terminal-submit">EXECUTE</button>
		</div>
    </form>

    <?php if ($target_url): ?>
        <?php echo $bucket_title; ?>

        <div id="terminal-body">
            <?php echo $bucket_body; ?>
        </div>

        <?php if (!empty($bucket_nav)): ?>
            <hr>
            <div class="section-header">// SYSTEM NAVIGATION</div>
            <ul>
                <?php echo $bucket_nav; ?>
            </ul>
        <?php endif; ?>
		
		<?php if (!empty($bucket_lists)): ?>
            <hr>
            <div class="section-header">// RELATED LINKS / DATA</div>
            <?php echo $bucket_lists; ?>
        <?php endif; ?>
		
    <br>
    <p>>> END OF TRANSMISSION <span class="blink">_</span></p>
    <?php endif; ?>
 <hr>  
	<b>[ &nbsp; </b><a href="?"><b>VISIT ANOTHER WEBSITE</b></a><b> &nbsp; ]</b> --- <b>[ &nbsp; </b><a href="#top"><b>TOP</b></a><b> &nbsp; ]</b>
    <hr>
<center>
		<br><br>
		<small>[ Try it on : <a href="vintage.php"> C64 / Atari / Psion / Palmtops / PocketPC / Nokia Communicator</a> ]</small>
        </center>
</div>

<script>
    // Méthode propre pour focus sans scroller
    window.addEventListener('load', function() {
        var input = document.getElementById('url-input');
        if (input) {
            // Focus l'élément mais empêche le défilement automatique vers lui
            input.focus({ preventScroll: true });
        }
        // Force la remontée en haut de page quoi qu'il arrive
        window.scrollTo(0, 0);
    });
</script>

</body>
</html>