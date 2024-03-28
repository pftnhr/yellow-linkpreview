# Linkpreview 0.8.16

Shows a link preview for selected links

<p align="center"><img src="linkpreview-screenshot.png" alt="Screenshot"></p>

## How to install the extension

[Download ZIP file](https://github.com/pftnhr/yellow-linkpreview/archive/refs/heads/main.zip) and copy it into your `system/extensions` folder. [Learn more about extensions](https://github.com/annaesvensson/yellow-update).

## How to show a link preview.

Create a link card that displays content from the `meta` properties of the linked website. If it has no `meta` properties, at least the `title` is displayed.

The link preview is only generated if it is inserted as a block element. If the shortcode is inadvertently inserted as an inline element, a normal link is generated.

### Appearance

You can influence the appearance of the paragraph by adapting the file `system/extensions/linkpreview.css` to your needs.

## Examples

Put a shortcode per link anywhere in your page

    [linkpreview https://example.com]

## Developer

Robert Pfotenhauer. [Get help](https://datenstrom.se/yellow/help/).

