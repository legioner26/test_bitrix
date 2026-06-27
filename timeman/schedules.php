<?

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

$bodyClass = $APPLICATION->GetPageProperty("BodyClass");
$APPLICATION->SetPageProperty("BodyClass", ($bodyClass ? $bodyClass." " : "")."page-one-column");

Loc::loadMessages($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/intranet/public_bitrix24/timeman/timeman.php");
$APPLICATION->SetTitle(Loc::getMessage("TITLE"));
$licenseType = "";
$isBitrix24Available = \Bitrix\Main\Loader::includeModule("bitrix24");
if ($isBitrix24Available)
{
	$licenseType = CBitrix24::getLicenseType();
}
?><?

if (ModuleManager::isModuleInstalled("timeman"))
{
	try
	{
		$APPLICATION->IncludeComponent("bitrix:timeman.schedules", "", []);
	}
	catch (\Bitrix\Main\AccessDeniedException $e)
	{
		echo $e->getMessage();
	}
}
elseif (
	$isBitrix24Available
	&& !(!ModuleManager::isModuleInstalled("timeman") && in_array($licenseType, ["company", "edu", "nfr"], true))
)
{
	if (LANGUAGE_ID == "de" || LANGUAGE_ID == "la")
	{
		$lang = LANGUAGE_ID;
	}
	else
	{
		$lang = LangSubst(LANGUAGE_ID);
	}
	?>
	<p><?= Loc::getMessage("TARIFF_RESTRICTION_TEXT") ?></p>
	<div style="text-align: center;"><img src="images/<?= $lang ?>/timeman.png"/></div>
	<p><?= Loc::getMessage("TARIFF_RESTRICTION_TEXT2") ?></p>
	<br/>
	<div style="text-align: center;"><?
		CBitrix24::showTariffRestrictionButtons("timeman") ?></div>
	<?
}
?>
<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>
