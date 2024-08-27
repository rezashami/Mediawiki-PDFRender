# PDFRender: A MediaWiki extension for showing a PDF slider.

## How to intall
First navigate to the mediawiki installed folder then run this commands:

```bash
  cd extensions/
  git clone https://github.com/rezashami/Mediawiki-PDFRender.git
```

## Load the extension
Add the name of the extension to the ```LocalSettings.php ``` file. 

For doing this, Please add below codes to the ```LocalSettings.php ``` :

```PHP
    wfLoadExtension('PDFRender');
    $wgPdfProcessor = '/usr/bin/gs'; 
    $wgPdfPostProcessor = '/usr/bin/convert';
    $wgFileExtensions = [ 'png', 'gif', 'jpg', 'jpeg', 'doc',
            'xls', 'mpp', 'pdf', 'ppt', 'tiff', 'bmp', 'docx', 'xlsx',
            'pptx', 'ps', 'odt', 'ods', 'odp', 'odg'
    ];

```
## Usage
After installation and loding the extension, for showing the PDF file slide by slide, just use the

 ```<pdfembed>File:given name.pdf</pdfembed>>```

tag code.

## Uninstal the Extension
For uninstal the extenstion, just remove the PDFRender folder in the ```extensions/``` folder. And then remove the added codes to the ```LocalSettings.php``` file.