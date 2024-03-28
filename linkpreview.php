<?php
// Linkpreview extension, https://github.com/pftnhr/yellow-linkpreview

class YellowLinkpreview {
    const VERSION = "0.8.16";
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
        $html = file_get_contents($url);
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
    
        // Extrahiere das Schema und den Hostnamen aus der URL
        $parsedUrl = parse_url($url);
        $preview['schema'] = $parsedUrl['scheme'] . '://';
        $preview['hostname'] = $parsedUrl['host'];
    
        // Überprüfe, ob ein Bild vorhanden ist und erhalte die Dimensionen
        if (!empty($preview['image'])) {
            $imageUrl = $preview['image'];
            if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $imageSize = @getimagesize($imageUrl);
                if ($imageSize !== false) {
                    $preview['imageWidth'] = $imageSize[0];
                    $preview['imageHeight'] = $imageSize[1];
                }
            }
        }
    
        return $preview;
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
