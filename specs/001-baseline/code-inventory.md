# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/html-to-word-bundle`  
**Last audited**: 2026-07-07

## Symfony config

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Core DI | FR-DI-001 |
| `Resources/config/html_to_word.yaml` | Default profile schema | FR-DI-001 |
| `Resources/config/nowo_html_to_word.yaml` | Shipped example profiles | FR-DI-001 |

## Bundle & DI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `HtmlToWordBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/HtmlToWordExtension.php` | DI extension | FR-CFG-002 |

## Config resolution

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Config/ProfileResolver.php` | Profile merge | FR-CFG-003 |
| `Config/ResolvedConfig.php` | Resolved profile DTO | FR-CFG-003 |

## Conversion pipeline

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Converter/HtmlToWordConverter.php` | Main converter | FR-CONV-001 |
| `Converter/HtmlToWordConverterInterface.php` | Converter contract | FR-CONV-001 |
| `Parser/HtmlParser.php` | HTML5 DOM parse | FR-PARSE-001 |
| `Parser/HtmlSanitizer.php` | HTML sanitization | FR-PARSE-001 |
| `Parser/RemoteHttpImageInliner.php` | Remote image fetch | FR-PARSE-002 |
| `Security/RemoteImageHostPolicy.php` | Allowed image hosts | FR-PARSE-002 |

## Engine & document model

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Engine/EngineRegistry.php` | Engine lookup | FR-ENG-001 |
| `Engine/PhpWordEngine.php` | PHPWord backend | FR-ENG-001 |
| `Engine/WordEngineInterface.php` | Engine contract | FR-ENG-001 |
| `Model/WordDocument.php` | In-memory document | FR-MODEL-001 |
| `Builder/WordDocumentBuilder.php` | Document assembly | FR-BUILD-001 |
| `Builder/SectionConfigurator.php` | Section setup | FR-BUILD-001 |
| `Builder/HeaderFooterBuilder.php` | Header/footer blocks | FR-BUILD-002 |
| `Builder/StyleMapper.php` | Inline style mapping | FR-BUILD-001 |
| `Builder/InlineComposer.php` | Inline run composer | FR-BUILD-001 |
| `Builder/ImageResolver.php` | Image path resolver | FR-BUILD-003 |
| `Builder/ImageResolverInterface.php` | Resolver contract | FR-BUILD-003 |
| `Builder/ImageSignatureValidator.php` | Image signature check | FR-BUILD-003 |
| `Builder/ImageStyleHelper.php` | Image dimension/style | FR-BUILD-003 |

## Transformers

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Transformer/TransformerInterface.php` | Transformer contract | FR-XFORM-001 |
| `Transformer/TransformerChain.php` | Tagged chain | FR-XFORM-001 |
| `Transformer/DocumentWalkerInterface.php` | Walker contract | FR-XFORM-001 |
| `Transformer/ParagraphTransformer.php` | `<p>` blocks | FR-XFORM-002 |
| `Transformer/HeadingTransformer.php` | Headings | FR-XFORM-002 |
| `Transformer/ListTransformer.php` | Lists | FR-XFORM-002 |
| `Transformer/TableTransformer.php` | Tables | FR-XFORM-002 |
| `Transformer/BlockquoteTransformer.php` | Blockquotes | FR-XFORM-002 |
| `Transformer/DivTransformer.php` | `<div>` blocks | FR-XFORM-002 |
| `Transformer/PreTransformer.php` | `<pre>` blocks | FR-XFORM-002 |
| `Transformer/HrTransformer.php` | Horizontal rules | FR-XFORM-002 |
| `Transformer/ImageBlockTransformer.php` | Block images | FR-XFORM-002 |

## Export

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Export/DocxExporter.php` | DOCX export | FR-EXPORT-001 |
| `Export/ExporterInterface.php` | Exporter contract | FR-EXPORT-001 |

## Exceptions

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Exception/HtmlToWordExceptionInterface.php` | Exception marker | FR-EXC-001 |
| `Exception/HtmlParseException.php` | Parse failures | FR-EXC-001 |
| `Exception/ExportException.php` | Export failures | FR-EXC-001 |
| `Exception/EngineNotAvailableException.php` | Missing engine | FR-EXC-001 |
| `Exception/UnknownEngineException.php` | Unknown engine id | FR-EXC-001 |
| `Exception/InvalidProfileException.php` | Bad profile | FR-EXC-001 |
| `Exception/UnsupportedElementException.php` | Unsupported tag | FR-EXC-001 |
| `Exception/ImageResolveException.php` | Image resolve failure | FR-EXC-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| Symfony config | 3 | 3 |
| Bundle & DI | 3 | 3 |
| Config resolution | 2 | 2 |
| Conversion pipeline | 6 | 6 |
| Engine & document model | 13 | 13 |
| Transformers | 12 | 12 |
| Export | 2 | 2 |
| Exceptions | 8 | 8 |
| **Total production sources** | **49** | **49** |
