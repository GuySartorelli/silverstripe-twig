<?php

namespace GuySartorelli\Twig;

use ArgumentCountError;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Path;
use SilverStripe\i18n\i18n;
use SilverStripe\View\SSViewer;
use SilverStripe\View\TemplateEngine;
use SilverStripe\View\TemplateGlobalProvider;
use SilverStripe\View\ThemeResourceLoader;
use SilverStripe\View\ViewLayerData;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use Twig\TwigFunction;

class TwigTemplateEngine implements TemplateEngine
{
    use Injectable;

    private string $template;

    private Environment $twig;

    /**
     * @internal
     */
    private static ?LoaderInterface $templateLoader = null;

    public function __construct(string|array $templateCandidates = [])
    {
        $this->twig = $this->getEngine();
    }

    public function setTemplate(string|array $templateCandidates): static
    {
        $this->template = TwigTemplateEngine::getTemplateFromCandidates($templateCandidates);
        return $this;
    }

    public function hasTemplate(string|array $templateCandidates): bool
    {
        return (bool) TwigTemplateEngine::getTemplateFromCandidates($templateCandidates);
    }

    public function renderString(string $template, ViewLayerData $model, array $overlay = [], bool $cache = true): string
    {
        return ''; // deal with this later
    }

    public function render(ViewLayerData $model, array $overlay = []): string
    {
        // if (!empty($arguments)) { // deal with overlay later
        //     $item = $item->customise($arguments); // This is what I did in the POC but it won't work now that model is a `ViewLayerData`.
        // }

        return $this->twig->render($this->template, [
            'model' => $model,
        ]);
    }

    private function getEngine()
    {
        $twig = new Environment(static::getLoader(), [
            'cache' => Path::join(TEMP_PATH, 'twig'), // TODO: Bust cache on flush and allow configurable cache adapters
            'auto_reload' => true, // could be configurable, probably want false in prod
            'autoescape' => false,
            // todo add debug as configurable option
        ]);

        // Everything in this method below this line should arguably be done in a twig extension.
        // Failure to do so could result in stale compiled templates after deployment if devs deploy but don't flush.
        // see https://twig.symfony.com/doc/3.x/advanced.html#extending-twig for more details

        // Template globals are available both as functions and global properties.
        $globalStuff = SSViewer::getMethodsFromProvider(
            TemplateGlobalProvider::class,
            'get_template_global_variables'
        );
        foreach ($globalStuff as $key => $stuff) {
            $twig->addFunction(new TwigFunction($key, $stuff['callable']));
            try {
                $twig->addGlobal($key, call_user_func($stuff['callable']));
            } catch (ArgumentCountError) {
                // This means it needs arguments, which means it's not a "global" in this sense.
                // no-op, twig will just ignore if you try to use it as a property in a template.
            }
        }

        // Use like `{% require javascript('my-script') %}` which maps nicely to the `<% require javascript('my-script') %>` from ss templates.
        $twig->addTokenParser(new RequireTokenParser());

        // Use like `{% base_tag xhtml %}` if your DOCTYPE indicates xhtml, or simply `{% base_tag %}` for regular html.
        $twig->addTokenParser(new BaseTagParser());

        // Use like `{{ _t('My.Localisation.Key', 'Default {var} goes here', {'var': 'string'}) }}`
        // Could alternatively use a token parser like with requirements above, which would allow for a syntax that more closely maps
        // to how localisation is done in ss templates, e.g: `{% t My.Translation.Key 'Default {var} goes here' var='string' %}`
        // but personally I don't like that syntax and that's more work, so I've done the simple thing for now.
        // Note that as per https://twig.symfony.com/doc/3.x/advanced.html#extending-twig `{{ }}` is for printing results of expressions, while `{% %}` is for executing statements
        // and therefore the `{{ }}` braces are more appropriate and therefore using a function is probably best.
        $twig->addFunction(new TwigFunction('_t', [i18n::class, '_t'], ['is_safe' => false]));

        return $twig;
    }

    private static function getLoader(): LoaderInterface
    {
        if (static::$templateLoader === null) {
            static::$templateLoader = new FilesystemLoader(TwigTemplateEngine::getTemplateDirs());
        }
        return static::$templateLoader;
    }

    private static function getTemplateDirs()
    {
        $themePaths = ThemeResourceLoader::inst()->getThemePaths(SSViewer::get_themes());
        $dirs = [];
        foreach ($themePaths as $themePath) {
            // Templates are in the normal `templates/` dirs - the .ss and .twig files live as siblings.
            $pathParts = [ BASE_PATH, $themePath, 'templates' ];
            $path = Path::join(...$pathParts);
            if (is_dir($path ?? '')) {
                $dirs[] = $path;
            }
        }
        return $dirs;
    }

    /**
     * Mostly copied from ThemeResourceLoader::findTemplate()
     */
    private static function getTemplateFromCandidates(string|array $template): ?string
    {
        $type = '';
        if (is_array($template)) {
            // Check if templates has type specified
            if (array_key_exists('type', $template ?? [])) {
                $type = $template['type'];
                unset($template['type']);
            }
            // Templates are either nested in 'templates' or just the rest of the list
            $templateList = array_key_exists('templates', $template ?? []) ? $template['templates'] : $template;
        } else {
            $templateList = [$template];
        }

        $twigLoader = TwigTemplateEngine::getLoader();
        foreach ($templateList as $i => $template) {
            // Check if passed list of templates in array format
            if (is_array($template)) {
                $path = TwigTemplateEngine::getTemplateFromCandidates($template);
                if ($path) {
                    return $path;
                }
                continue;
            }

            // If we have an .twig extension, this is a path, not a template name. We should
            // pass in templates without extensions in order for template manifest to find
            // files dynamically.
            if (substr($template ?? '', -3) == '.twig' && file_exists($template ?? '')) {
                return $template; // Dunno if this actually works with twig
            }

            // Check string template identifier
            $template = str_replace('\\', '/', $template ?? '');
            $parts = explode('/', $template ?? '');

            $tail = array_pop($parts);
            $head = implode('/', $parts);
            $path = Path::join([$head, $type, $tail]) . '.twig';
            if ($twigLoader->exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
