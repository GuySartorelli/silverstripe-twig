# Silverstripe Twig

Provides a bridge between Silverstripe CMS 6+ and [`twig/twig`](https://github.com/twigphp/Twig/).

> [!IMPORTANT]
> This is an _extremely_ experimental module which primarily (if not exclusively) exists to validate the abstraction from `SSViewer` to using the new `TemplateEngine` interface can be used with engines for other template syntaxes.
> If you want or need this functionality, I encourage you to do one of the following:
>
> 1. Use this module as inspiration to create and maintain your _own_ module
> 1. Open an issue expressing your use case and request that this module be made stable
>
> If you go with 2 above, be prepared to put in some work providing PRs to get this module into a well-tested and stable state.

## Installation

This package is not added to packagist. You will need to add the git repo to the `repositories` array in your `composer.json` file. The first line below will do this for you.

```bash
composer config repositories.silverstripe-twig --json '{"type":"vcs", "url":"git@github.com:GuySartorelli/silverstripe-twig.git"}'
composer require guysartorelli/silverstripe-twig:dev-main
```

## Usage

In your templates, you need to use `model` anywhere you want the currently controller/model in scope. For example where you'd normally directly use `$Title` in a ss template, you must use `{{ model.Title }}` in the twig template.

You must not use `model` for global template variables (e.g. `$SiteConfig.Title` is simply `{{ SiteConfig.Title }}`).

Casting and escaping is done using Silverstripe CMS's `DBField` classes - Twig's autoescaping has been turned off. That means if you want to escape an HTML field, you should usually use `{{ model.MyField.Xml }}` instead of `{{ model.MyField|e }}`. Similarly, to get the raw value of a field use `{{ model.MyField.Raw }}` instead of `{{ model.MyField|raw }}`.

Don't use twig's [`asset()` function](https://symfony.com/doc/current/templates.html#templates-link-to-assets) - instead use `{% require %}` the same way you would use [`<% require %>`](https://docs.silverstripe.org/en/developer_guides/templates/requirements/#template-requirements-api) e.g. `{% require themedCSS("some-file.css") %}`

Don't use twig's localisation syntax - instead use `{{ _t }}` e.g. `{{ _t('My.Localisation.Key', 'Default {var} goes here', {'var': 'string'}) }}`. This maps more closely to the PHP `_t()` function than it does Silverstripe CMS's ss template syntax for localisations.

Twig doesn't call `exists()` or `count()` on a list to see if it's got content. So `{% if model.MyList %}` will always return truthy.

You **CANNOT** use any method/property that returns a scalar directly in a twig `{% if %}` condition. `ViewLayerData` will return a `ViewLayerData` wrapping a `DBBoolean` wrapping the actual value. Since `ViewLayerData` is an object which is truthy, twig will just treat it as true even if the value contained in the `DBBoolean` is false. Even with conditions, it'll be trying to compare `ViewLayerData` which will usually result in an error.
The one exception to this is `count()` which is explicitly implemented on `ViewLayerData`.
You can work around this using `getRawDataValue()`, e.g. `{% if model.getRawDataValue('MyField') %}`

There are a few ways to swap out rendering engines as of the time of writing:

### Globally override the normal one via dependency injection

This is the lowest effort in terms of setting the engine, but it means _every single template_ used in your project must be a twig template. I'm considering making a .ss to twig conversion package, but for now you'd have to convert them all by hand.

```yml
---
After: '#view-config'
---
SilverStripe\Core\Injector\Injector:
  SilverStripe\View\TemplateEngine:
    class: 'GuySartorelli\Twig\TwigTemplateEngine'
```

Note that this will also be used by default for all string template rendering unless I change how that works in framework. So that may cause problems for you in a few places.

### For a specific controller or model

#### Controllers

It's possible I'll change how this works - likely adding a config property for it - but for now you'd need to override the `getTemplateEngine()` method on your controller.

```php
use GuySartorelli\Twig\TwigTemplateEngine;
use SilverStripe\Control\Controller;

class MyController extends Controller
{
    // ...

    protected function getTemplateEngine(): TemplateEngine
    {
        if (!$this->templateEngine) {
            $this->templateEngine = TwigTemplateEngine::create();
        }
        return $this->templateEngine;
    }
}
```

When this controller is rendered (e.g. via `handleAction()` or directly calling `render()`), the twig template engine will be used to render it. You must make sure you have a twig template available.

All includes invoked in your template will also be rendered by the twig template engine - but if another controller is invoked (e.g. rendering an elemental area), it will use whatever engine is set for it.

#### Models

There's no `getTemplateEngine()` method in `ModelData`. Instead, you'll need to override `renderWith()` like so:

```php
use GuySartorelli\Twig\TwigTemplateEngine;
use SilverStripe\Model\ModelData;
use SilverStripe\ORM\FieldType\DBHTMLText;

class MyModel extends ModelData
{
    // ...

    public function renderWith($template, ModelData|array $customFields = []): DBHTMLText
    {
        // Ensure MY template engine is used, unless the viewer was already explicitly instantiated
        if (!($template instanceof SSViewer)) {
            $template = SSViewer::create($template, TwigTemplateEngine::create());
        }
        return parent::renderWith($template, $customFields);
    }
}
```

### For a given instance of `SSViewer`

If you're instantiating `SSViewer` instances directly, you can pass the template engine as the second constructor arg.

```php
use GuySartorelli\Twig\TwigTemplateEngine;
use SilverStripe\View\SSViewer;

// ...
$viewer = SSViewer::create($templates, TwigTemplateEngine::create());
```
