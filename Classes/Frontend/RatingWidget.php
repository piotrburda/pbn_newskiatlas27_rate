<?php

declare(strict_types=1);

namespace Pbn\PbnNewskiatlas27Rate\Frontend;

use Pbn\PbnNewskiatlas27Rate\Configuration\RateSettings;
use Pbn\PbnNewskiatlas27Rate\Domain\Repository\QuestionRepository;
use Pbn\PbnNewskiatlas27Rate\Service\ResultService;
use Pbn\PbnNewskiatlas27Rate\Service\TargetService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * USER object rendering the rating widget. Output is cached with the page and
 * tagged "pbnrate_<table>_<uid>"; the tag is flushed after every new vote.
 *
 * Usage in Fluid:
 *   <f:cObject typoscriptObjectPath="lib.pbnNewskiatlas27Rate"
 *              data="{table: 'tx_pbnnewskiatlas27_domain_model_resort', uid: resort.uid}" />
 */
class RatingWidget
{
    /** @var ContentObjectRenderer|null set by ContentObjectRenderer::callUserFunction() */
    public $cObj;

    public function render(string $content, array $conf): string
    {
        return $this->renderTemplate($conf, 'Widget');
    }

    public function renderSummary(string $content, array $conf): string
    {
        return $this->renderTemplate($conf, 'Summary');
    }

    protected function renderTemplate(array $conf, string $templateName): string
    {
        $settings = RateSettings::fromTypoScript($conf);
        list($table, $uid) = $this->resolveTarget($conf, $settings);

        $targetService = GeneralUtility::makeInstance(TargetService::class);
        if (!$targetService->isValidTarget($settings, $table, $uid)) {
            return '';
        }

        $questionRepository = GeneralUtility::makeInstance(QuestionRepository::class);
        $groups = $questionRepository->findGroupedForTable($settings->questionStoragePids, $table);
        if ($groups === array()) {
            return '';
        }

        $questions = $questionRepository->flatten($groups);
        $resultService = GeneralUtility::makeInstance(ResultService::class);
        $results = $resultService->build($settings, $table, $uid, $questions, (int)$GLOBALS['EXEC_TIME']);

        $summaryQuestion = $resultService->findSummaryQuestion($questions);
        if ($summaryQuestion !== null) {
            $summaryQuestion['result'] = $results['questions'][(int)$summaryQuestion['uid']] ?? null;
        }

        $this->addCacheTag(TargetService::cacheTag($table, $uid));
        if ($templateName === 'Widget') {
            $this->includeAssets($settings);
        } elseif ($settings->includeCss) {
            $this->includeAssets($settings, false);
        }

        $view = $this->createView($conf, $templateName);
        $view->assignMultiple(array(
            'rating' => array(
                'id' => 'pbnrate-' . substr(md5($table), 0, 6) . '-' . $uid,
                'table' => $table,
                'uid' => $uid,
                'endpoint' => $this->buildEndpointUrl($settings),
                'groups' => $resultService->mergeIntoGroups($groups, $results),
                'results' => $results,
                'summary' => $summaryQuestion,
                'maxStars' => $settings->maxStars,
                'starValues' => $settings->getStarValues(),
                'lockDays' => (int)round($settings->lockPeriod / 86400),
                'lockHours' => (int)round($settings->lockPeriod / 3600),
            ),
            'settings' => $conf['settings.'] ?? array(),
        ));

        return (string)$view->render();
    }

    /**
     * @return array{0: string, 1: int}
     */
    protected function resolveTarget(array $conf, RateSettings $settings): array
    {
        $data = $this->cObj instanceof ContentObjectRenderer && is_array($this->cObj->data) ? $this->cObj->data : array();
        // When the object is used outside f:cObject (e.g. inside tt_content), data is a DB row:
        // never guess the table in that case.
        $isDatabaseRow = array_key_exists('pid', $data);

        $table = $this->stdWrapValue($conf, 'table');
        if ($table === '' && !$isDatabaseRow) {
            $table = trim((string)($data['table'] ?? ''));
            if ($table === '') {
                $table = $settings->getDefaultTable();
            }
        }

        $uid = (int)$this->stdWrapValue($conf, 'uid');
        if ($uid <= 0 && !$isDatabaseRow) {
            $uid = (int)($data['uid'] ?? 0);
        }

        return array($table, $uid);
    }

    protected function stdWrapValue(array $conf, string $key): string
    {
        $value = (string)($conf[$key] ?? '');
        if (isset($conf[$key . '.']) && is_array($conf[$key . '.']) && $this->cObj instanceof ContentObjectRenderer) {
            $value = (string)$this->cObj->stdWrap($value, $conf[$key . '.']);
        }

        return trim($value);
    }

    protected function buildEndpointUrl(RateSettings $settings): string
    {
        $pageId = $settings->ajaxPid > 0 ? $settings->ajaxPid : (int)$GLOBALS['TSFE']->id;
        $cObj = $this->cObj instanceof ContentObjectRenderer
            ? $this->cObj
            : GeneralUtility::makeInstance(ContentObjectRenderer::class);

        return (string)$cObj->typoLink_URL(array(
            'parameter' => $pageId . ',' . $settings->ajaxTypeNum,
        ));
    }

    protected function createView(array $conf, string $templateName): StandaloneView
    {
        $viewConf = isset($conf['view.']) && is_array($conf['view.']) ? $conf['view.'] : array();
        $templatePaths = $this->cleanPaths($viewConf['templateRootPaths.'] ?? array(), 'EXT:pbn_newskiatlas27_rate/Resources/Private/Templates/');
        $partialPaths = $this->cleanPaths($viewConf['partialRootPaths.'] ?? array(), 'EXT:pbn_newskiatlas27_rate/Resources/Private/Partials/');

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->getRequest()->setControllerExtensionName('PbnNewskiatlas27Rate');
        $view->setTemplateRootPaths($templatePaths);
        $view->setPartialRootPaths($partialPaths);
        $view->setTemplatePathAndFilename($this->findTemplate($templatePaths, $templateName));

        return $view;
    }

    /**
     * @return string[] ordered by key, empty entries removed
     */
    protected function cleanPaths($paths, string $fallback): array
    {
        $clean = array();
        if (is_array($paths)) {
            ksort($paths, SORT_NUMERIC);
            foreach ($paths as $key => $path) {
                if (is_string($path) && trim($path) !== '') {
                    $clean[(int)$key] = rtrim(trim($path), '/') . '/';
                }
            }
        }

        return $clean !== array() ? $clean : array(0 => $fallback);
    }

    protected function findTemplate(array $templatePaths, string $templateName): string
    {
        foreach (array_reverse($templatePaths, true) as $path) {
            $file = GeneralUtility::getFileAbsFileName($path . $templateName . '.html');
            if ($file !== '' && is_file($file)) {
                return $file;
            }
        }

        return GeneralUtility::getFileAbsFileName(
            'EXT:pbn_newskiatlas27_rate/Resources/Private/Templates/' . $templateName . '.html'
        );
    }

    protected function includeAssets(RateSettings $settings, bool $withJs = true): void
    {
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        if ($settings->includeCss) {
            $pageRenderer->addCssFile($this->webPath('EXT:pbn_newskiatlas27_rate/Resources/Public/Css/rate.css'));
        }
        if ($withJs && $settings->includeJs) {
            $pageRenderer->addJsFooterFile($this->webPath('EXT:pbn_newskiatlas27_rate/Resources/Public/JavaScript/rate.js'));
        }
    }

    protected function webPath(string $extPath): string
    {
        return PathUtility::getAbsoluteWebPath(GeneralUtility::getFileAbsFileName($extPath));
    }

    protected function addCacheTag(string $tag): void
    {
        if (isset($GLOBALS['TSFE']) && is_object($GLOBALS['TSFE'])) {
            $GLOBALS['TSFE']->addCacheTags(array($tag));
        }
    }
}
