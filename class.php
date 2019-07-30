<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Iblock,
	Bitrix\Main\Context,
	Bitrix\Main\Loader,
	Bitrix\Main\Localization\Loc,
	Bitrix\Main\Data\Cache,
	Bitrix\Main\Application;

Loc::loadMessages(__FILE__);

if (!Loader::includeModule('iblock')) {
	ShowError(Loc::getMessage('FLXMD_SEO_MODULE_IN_MODULE_NOT_FOUND'));
	return;
}

class FLXMD_SeoParametersSet extends CBitrixComponent
{

	private $iblockId = 14;

	public function executeComponent() {

		$sUrl = $_SERVER["REQUEST_URI"];
		if (!empty($_SERVER["QUERY_STRING"])) {
			$sUrl = str_replace("?" . $_SERVER["QUERY_STRING"], "", $_SERVER["REQUEST_URI"]);
		}

		if (
			strripos($sUrl, '/filter/') &&
			strripos($sUrl, '/apply/') &&
			!strripos($sUrl, '/clear/') &&
			count(explode('/', $sUrl)) < 7 &&
			!strripos($sUrl, '-or-')
		) {
			$this->findLinkIblock($sUrl);
			$this->setPageParameters();
		} else if (
			strripos($sUrl, '/filter/') &&
			strripos($sUrl, '/apply/') &&
			count(explode('/', $sUrl)) > 6 ||
			strripos($sUrl, '-or-')
		) {
			$this->setMetaRobotsTag();
		}

	}

	protected function findLinkIblock($link) {

		$cache = Cache::createInstance();

		$sCacheID = "filter_blog_".serialize($link);

		if ($cache->initCache(86400, $sCacheID)) {

			$this->arResult = $cache->getVars();

		} elseif ($cache->startDataCache()) {

			$objResult = CIBlockElement::GetList(
				array(),
				array(
					'ACTIVE' => 'Y',
					'IBLOCK_ID' => $this->iblockId,
					'=NAME' => $link
				),
				false,
				false,
				array(
					'NAME', 'PROPERTY_TITLE', 'PROPERTY_H1', 'PROPERTY_DESCRIPTION', 'PROPERTY_CANONICAL_THIS', 'PROPERTY_CANONICAL_TEXT', 'PROPERTY_SEO_TEXT', 'PROPERTY_META_TAG'
				)
			);

			while ($dbResult = $objResult->Fetch()) {

				$this->arResult = array(
					'TITLE' => $dbResult['PROPERTY_TITLE_VALUE'],
					'H1' => $dbResult['PROPERTY_H1_VALUE'],
					'DESCRIPTION' => $dbResult['PROPERTY_DESCRIPTION_VALUE']['TEXT'],
					'CANONICAL_THIS' => $dbResult['PROPERTY_CANONICAL_THIS_VALUE'],
					'CANONICAL_VALUE' => $dbResult['PROPERTY_CANONICAL_TEXT_VALUE'],
					'SEO_TEXT' => $dbResult['PROPERTY_SEO_TEXT_VALUE']['TEXT'],
					'META_TAG' => $dbResult['PROPERTY_META_TAG_VALUE']
				);

			}

			$cache->endDataCache($this->arResult);

		}

	}

	protected function setPageParameters() {

		global $APPLICATION;

		if (!empty($this->arResult)) {

			$APPLICATION->SetTitle($this->arResult["TITLE"]);
			$APPLICATION->SetPageProperty("title", $this->arResult["TITLE"]);
			$APPLICATION->SetPageProperty("h1", $this->arResult["H1"]);
			$APPLICATION->SetPageProperty("description", $this->arResult["DESCRIPTION"]);
			$APPLICATION->SetPageProperty("seo_text", $this->arResult["SEO_TEXT"]);

			$this->getServerParameters();
			$this->getGetParameters();

			if (
				$this->arResult['CANONICAL_THIS'] == 'Y' &&
				$this->arResult['CANONICAL_VALUE'] == ''
			) {

				$urlCanonical = $this->arResult['HTTP_X_FORWARDED_PROTO'].'://'.$this->arResult['HTTP_HOST'].$APPLICATION->GetCurPage(false);

 			} else if ( !empty($this->arResult['CANONICAL_VALUE'])) {

				$urlCanonical = $this->arResult['HTTP_X_FORWARDED_PROTO'].'://'.$this->arResult['HTTP_HOST'].$this->arResult['CANONICAL_VALUE'];

			}

			if ($this->arResult['ITEMS_MATH']) {
				$urlCanonical .= '?' . key($this->arResult['ITEMS_MATH']) . '=' . reset($this->arResult['ITEMS_MATH']);
				$APPLICATION->SetPageProperty("seo_text", '');
			}

			$APPLICATION->SetPageProperty("canonical", $urlCanonical);

			if (!empty($this->arResult['META_TAG'])) {
				$APPLICATION->AddHeadString('<meta name="robots" content="'.$this->arResult['META_TAG'].'" />', true);
			}

		}

	}

	protected function getServerParameters() {

		$this->arResult['HTTP_HOST'] = Context::getCurrent()->getServer()->get('HTTP_HOST');
		$this->arResult['HTTP_X_FORWARDED_PROTO'] = Context::getCurrent()->getServer()->get('HTTP_X_FORWARDED_PROTO');

	}

	protected function getGetParameters() {

		$this->arResult['GET'] = Application::getInstance()->getContext()->getRequest()->getQueryList()->toArray();
		$this->arResult['ITEMS_MATH'] = array_intersect_key($this->arResult['GET'], array('PAGEN_1' => ''));

	}

	protected function setMetaRobotsTag() {
		global $APPLICATION;
		$APPLICATION->AddHeadString('<meta name="robots" content="noindex,nofollow" />', true);
	}
}
?>
