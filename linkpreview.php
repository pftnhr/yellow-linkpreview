<?php
// Linkpreview extension, https://github.com/pftnhr/yellow-linkpreview

class YellowLinkpreview {
    const VERSION = "0.8.17";
    public $yellow;         // access to API

    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
    }

    // Handle page content of shortcut
    public function onParseContentShortcut($page, $name, $text, $type) {
        $output = null;        
        list($url) = $this->yellow->toolbox->getTextArguments($text);
        if ($name == "linkpreview" && ($type == "block")) {
            $linkPreview = $this->getLinkPreview($url);
            
            $output = $this->displayLinkPreview($url, $linkPreview);
        } elseif ($name == "linkpreview" && ($type == "inline")) {
            $output = "<a href=\"" . $url . "\">" . $url . "</a>";
        }
        return $output;
    }
    
    public function displayLinkPreview($url, $linkPreview) {
        $output = null;
        if (!empty($linkPreview)) {
            $output .= "<div>";
            $output .= "<a href=\"" . $url . "\" class=\"linkpreview\" target=\"_blank\" rel=\"noopener noreferrer\">";
            if (!empty($linkPreview['image'])) {
                $output .= "<div class=\"linkpreview__image\">";
                $output .= "<img src=\"" . $linkPreview['image'] . "\" width=\"" . $linkPreview['imageWidth'] . "\"  height=\"" . $linkPreview['imageHeight'] . "\" alt=\"" . $linkPreview['title'] . "\">";
                $output .= "</div>";
            }
            $output .= "<div class=\"linkpreview__content\">";
            if (!empty($linkPreview['site_name'])) {
                $output .= "<span class=\"linkpreview__content__host\">" . $linkPreview['site_name'] . "</span>";
            }
            $output .= "<span class=\"linkpreview__content__title\">" . $linkPreview['title'] . "</span>";
            if (!empty($linkPreview['description'])) {
                $output .= "<span class=\"linkpreview__content__description\">" . $linkPreview['description'] . "</span>";
            }
            $output .= "</div>";
            $output .= "</a>";
            $output .= "</div>";
        } else {
            $output .= "<a href=\"" . $url . "\">" . $url . "</a>";
        }
        return $output;
    }
    
    public function getLinkPreview($url) {
        // Cache-Datei lesen
        $cacheFile = $this->yellow->system->get("coreExtensionDirectory") . "linkpreview.json";
        $previews = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : array();
        
        // Überprüfen, ob der Cache-Eintrag für die URL vorhanden ist und nicht abgelaufen ist
        if (isset($previews[$url]) && time() - $previews[$url]['timestamp'] <= 86400) {
            return $previews[$url];
        }

        // Mit cURL den HTML-Inhalt der URL abrufen
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYPEER => false, // speed up
            CURLOPT_USERAGENT => "Mozilla/5.0 (compatible; YellowLinkpreview " . YellowLinkpreview::VERSION . "; LinkChecker; +https://github.com/pftnhr/yellow-linkpreview) ",
            CURLOPT_USERAGENT=>$this->yellow->toolbox->getServer('HTTP_USER_AGENT'), // for paranoid servers
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30, // Timeout nach 30 Sekunden
        ]);
        $html = curl_exec($curl);
        curl_close($curl);
    
        if ($html === false) {
            return array(); // Rückgabe eines leeren Arrays im Fehlerfall
        }
    
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
    
        $metas = $doc->getElementsByTagName('meta');
        $preview = array();
        $ogFound = false;
        foreach ($metas as $meta) {
            if ($meta->hasAttribute('property') && strpos($meta->getAttribute('property'), 'og:') === 0) {
                $ogFound = true;
                $property = str_replace('og:', '', $meta->getAttribute('property'));
                $content = $meta->getAttribute('content');
                $preview[$property] = $content;
            }
        }
    
        // Überprüfe, ob Open Graph-Metadaten gefunden wurden
        if (!$ogFound) {
            // Wenn keine Open Graph-Metadaten gefunden wurden, versuche alternative Metatags zu verwenden
            $preview['title'] = $doc->getElementsByTagName('title')->item(0)->nodeValue;
            foreach ($metas as $meta) {
                if ($meta->hasAttribute('name')) {
                    $name = strtolower($meta->getAttribute('name'));
                    if ($name == 'description') {
                        $preview['description'] = $meta->getAttribute('content');
                    }
                }
            }
        }
    
        if (!empty($preview['image'])) {
            $imageUrl = $this->absoluteUrl($preview['image'], $url);
            if ($imageUrl !== false) {
                $imageSize = @getimagesize($imageUrl);
                if ($imageSize !== false) {
                    $preview['image'] = $imageUrl; // Aktualisiere die Bild-URL zu einer absoluten URL
                    $preview['imageWidth'] = intval($imageSize[0]); // Konvertiere Breite in Ganzzahl
                    $preview['imageHeight'] = intval($imageSize[1]); // Konvertiere Höhe in Ganzzahl
                } else {
                    // Bildabmessungen konnten nicht abgerufen werden, setze Standardabmessungen
                    $preview['imageWidth'] = 300; // Setze hier die Standardbreite
                    $preview['imageHeight'] = 200; // Setze hier die Standardhöhe
                }
            } else {
                // Fehler beim Umwandeln der relativen Bild-URL in eine absolute URL
                unset($preview['image']); // Entferne das Bild, um Fehler zu vermeiden
            }
        }
        
        // Hinzufügen der aktuellen Vorschau zu den Cache-Daten
        $previewEntry = array(
            "locale" => isset($preview['locale']) ? $preview['locale'] : null,
            "type" => isset($preview['type']) ? $preview['type'] : null,
            "title" => isset($preview['title']) ? $preview['title'] : null,
            "description" => isset($preview['description']) ? $preview['description'] : null,
            "url" => isset($preview['url']) ? $preview['url'] : null,
            "site_name" => isset($preview['site_name']) ? $preview['site_name'] : null,
            "image" => isset($preview['image']) ? $preview['image'] : null,
            "image:width" => isset($preview['image:width']) ? intval($preview['image:width']) : null,
            "image:height" => isset($preview['image:height']) ? intval($preview['image:height']) : null,
            "imageWidth" => isset($preview['imageWidth']) ? $preview['imageWidth'] : null,
            "imageHeight" => isset($preview['imageHeight']) ? $preview['imageHeight'] : null,
            "timestamp" => time()
        );        
        
        // Cache-Eintrag hinzufügen
        $previews[$url] = $previewEntry; // Definiere das Feld 'data'
        
        // Cache-Daten in die Datei schreiben
        file_put_contents($cacheFile, json_encode($previews, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    
        return $preview;
    }

    // Konvertiert eine relative URL in eine absolute URL basierend auf der Basis-URL
    private function absoluteUrl($relativeUrl, $baseUrl) {
        $urlParts = parse_url($baseUrl);
        $scheme = isset($urlParts['scheme']) ? $urlParts['scheme'] . '://' : '';
        $host = isset($urlParts['host']) ? $urlParts['host'] : '';
        $port = isset($urlParts['port']) ? ':' . $urlParts['port'] : '';
        $path = isset($urlParts['path']) ? $urlParts['path'] : '';
        $path = substr($path, 0, strrpos($path, '/') + 1); // entferne Dateiname aus dem Pfad
    
        // Wenn die relative URL bereits eine absolute URL ist, gibt sie einfach zurück
        if (parse_url($relativeUrl, PHP_URL_SCHEME) != '') return $relativeUrl;
    
        // Wenn die relative URL mit einem Slash beginnt, wird sie an den Basis-URL-Pfad angehängt
        if (substr($relativeUrl, 0, 1) == '/') {
            return $scheme . $host . $port . $relativeUrl;
        }
    
        // Wenn die relative URL mit Punktpunktschreibweise beginnt, wird sie an den Basis-URL-Pfad angehängt
        if (substr($relativeUrl, 0, 3) == '../') {
            while (substr($relativeUrl, 0, 3) == '../') {
                $path = substr($path, 0, strrpos(rtrim($path, '/'), '/')) . '/';
                $relativeUrl = substr($relativeUrl, 3);
            }
            return $scheme . $host . $port . $path . $relativeUrl;
        }
    
        // Ansonsten wird die relative URL an den Basis-URL-Pfad angehängt
        return $scheme . $host . $port . $path . $relativeUrl;
    }
    
    // Handle page extra data
    public function onParsePageExtra($page, $name) {
        $output = null;
        if ($name=="header") {
            $extensionLocation = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("coreExtensionLocation");
            $output = "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"{$extensionLocation}linkpreview.css\">\n";
        }
        return $output;
    }
}
