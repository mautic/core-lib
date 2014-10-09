<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

$locale       = $app->getRequest()->getLocale();
$dataText     = (!empty($settings['text']))      ? ' data-text="'.$settings['text'].'"'         : '';
$dataVia      = (!empty($settings['via']))       ? ' data-via="'.$settings['via'].'"'           : '';
$dataRelated  = (!empty($settings['related']))   ? ' data-related="'.$settings['related'].'"'   : '';
$dataHashtags = (!empty($settings['hashtags']))  ? ' data-hashtags="'.$settings['hashtags'].'"' : '';
$dataSize     = (!empty($settings['size']))      ? ' data-size="'.$settings['size'].'"'         : '';
$dataCount    = (!empty($settings['count']))     ? ' data-count="'.$settings['count'].'"'       : '';
$dataLang     = ($locale != 'en_US')                 ? ' data-lang="'.$locale.'"'                       : '';

$js = <<<JS
!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');
JS;
$view['assets']->addScriptDeclaration($js, 'bodyClose');
?>

<div class="share-button twitter-share-button layout-<?php echo $settings['count']; ?>">
    <a href="https://twitter.com/share"
    class="twitter-share-button share-button"<?php echo $dataText.$dataVia.$dataRelated.$dataHashtags.$dataSize.$dataCount; ?>>Tweet</a>
</div>