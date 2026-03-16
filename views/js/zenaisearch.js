/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    ZenAI Software
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
/* global $, zenaiSearchConfig */
$(function () {
    var $searchWidget = $('#search_widget');
    if (!$searchWidget.length) {
        return;
    }

    var $form = $searchWidget.find('form').first();
    var $searchInput = $form.find('input[name="s"], input[name="search_query"]').first();

    if (!$form.length || !$searchInput.length) {
        return;
    }

    var config = window.zenaiSearchConfig || {};
    var storageKey = config.modeStorageKey || 'zenaiSearch.mode';
    var defaultMode = config.defaultMode || 'ai';
    var labels = {
        ai: config.aiLabel || 'AI Mode',
        classic: config.searchLabel || 'Search'
    };

    var $modeWrap = $('<div class="zenai-search-mode"></div>');
    var $modeSelect = $('<select class="zenai-search-select" aria-label="Search mode"></select>');
    $modeSelect.append($('<option></option>').val('ai').text(labels.ai));
    $modeSelect.append($('<option></option>').val('classic').text(labels.classic));
    $modeWrap.append($modeSelect);

    $form.append($modeWrap);

    var modeFromUrl = getModeFromUrl();
    var mode = modeFromUrl || loadMode() || defaultMode;

    if (mode !== 'ai' && mode !== 'classic') {
        mode = defaultMode;
    }

    $modeSelect.val(mode);
    syncHiddenField();
    updateSuggestBehavior();

    $modeSelect.on('change', function () {
        persistMode($modeSelect.val());
        syncHiddenField();
        updateSuggestBehavior();
    });

    $form.on('submit', function () {
        persistMode($modeSelect.val());
        syncHiddenField();
    });

    function loadMode() {
        try {
            return window.localStorage ? window.localStorage.getItem(storageKey) : null;
        } catch (e) {
            return null;
        }
    }

    function persistMode(value) {
        try {
            if (window.localStorage) {
                window.localStorage.setItem(storageKey, value);
            }
        } catch (e) {
            // Ignore storage errors
        }
    }

    function syncHiddenField() {
        var $hidden = $form.find('input[name="zenai"]');

        if ($modeSelect.val() === 'ai') {
            if (!$hidden.length) {
                $hidden = $('<input type="hidden" name="zenai" value="1">');
                $form.append($hidden);
            }
            $hidden.val('1');
            return;
        }

        if ($hidden.length) {
            $hidden.remove();
        }
    }

    function updateSuggestBehavior() {
        var isAiMode = $modeSelect.val() === 'ai';

        if (typeof $searchInput.psBlockSearchAutocomplete !== 'function') {
            return;
        }

        try {
            if (isAiMode) {
                $searchInput.psBlockSearchAutocomplete('disable');
                $('.searchbar-autocomplete').empty().hide();
            } else {
                $searchInput.psBlockSearchAutocomplete('enable');
                $('.searchbar-autocomplete').show();
            }
        } catch (e) {
            // Ignore if widget is not initialized yet
        }
    }

    function getModeFromUrl() {
        try {
            var params = new URLSearchParams(window.location.search || '');
            return params.get('zenai') === '1' ? 'ai' : null;
        } catch (e) {
            return null;
        }
    }
});
