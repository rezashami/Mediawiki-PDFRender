# PDFRender: A MediaWiki extension for showing a PDF slider.

## How to install
First, navigate to the Mediawiki installed folder then run these commands:

```bash
  cd extensions/
  git clone https://github.com/rezashami/Mediawiki-PDFRender.git
```

## Load the extension
Add the name of the extension to the ```LocalSettings.php ``` file. 

To do this, Please add the below codes to the ```LocalSettings.php ``` :

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
After installation and loading the extension, to show the PDF file slide by slide, use the

 ```<pdfembed>File:given name.pdf</pdfembed>```

tag code.

## Uninstall the Extension
To uninstall the extension, remove the PDFRender folder in the ```extensions/``` folder. Then, remove the added codes from the ```LocalSettings.php``` file.
