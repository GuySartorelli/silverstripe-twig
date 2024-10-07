<?php

namespace GuySartorelli\Twig;

use ArgumentCountError;
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
        // @TODO Handle arrays properly.
        if (is_array($templateCandidates)) {
            $templateCandidates = $templateCandidates[array_key_first($templateCandidates)];
        } // UGH
        $name = $template->getName();
        if (!str_ends_with($name, '.twig')) {
            $name .= '.twig';
        }
        // @TODO might need to handle absolute or relative paths that already include the type?
        $this->template = Path::join($template->getType(), $name);
    }

    public function hasTemplate(string|array $templateCandidates): bool
    {
    }

    public function renderString(string $template, ViewLayerData $model, array $overlay = [], bool $cache = true): string
    {

    }

    public function render(ViewLayerData $model, array $overlay = []): string
    {
        // if (!empty($arguments)) {
        //     $item = $item->customise($arguments);
        // }

        return $this->twig->render($this->template, [
            'model' => $model,
        ]);
    }

    private function getEngine()
    {
        $twig = new Environment(static::getLoader(), [
            'cache' => Path::join(TEMP_PATH, 'twig'), // TODO: Bust cache on flush
            'auto_reload' => true, // could be configurable, might want false in prod
            'autoescape' => false,
            // could add debug as configurable option
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
            static::$templateLoader = new FilesystemLoader(static::getTemplateDirs());
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
}
