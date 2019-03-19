# ARCHE TEI coverter dissemination service

Converts TEI XMLs to other formats using the [oxgarage](https://github.com/sebastianrahtz/oxgarage).

Usage - just make a GET request to `{documentRoot}/{format}/{ARCHEid}`, where

* `{documentRoot}` a place where you cloned this repo
* `{format}` one of output formats as specified in the `config.ini` (a sample config contains settings for docx, odt, pdf and html)
* `{ARCHEid}` short or fully-qualified ARCHE resource's id, e.g. `tunico-corpus/11_souq_salesman.xml` or `https://id.acdh.oeaw.ac.at/tunico-corpus/11_souq_salesman.xml`

## Installation

* clone the repo on a server with PHP 7
* copy `config_sample.ini` to `config.ini` and adjust

