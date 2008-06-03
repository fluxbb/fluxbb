<?php
if (!defined('FORUM_PAGE') || (substr(FORUM_PAGE, 0, 5) != 'admin'))
{
?>
<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $base_url.'/style/'.$forum_user['style'] ?>/Oxygen.css" />
<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $base_url.'/style/'.$forum_user['style'] ?>/Oxygen_cs.css" />
<!--[if lte IE 6]><link rel="stylesheet" type="text/css" href="<?php echo $base_url.'/style/'.$forum_user['style'] ?>/Oxygen_ie6.css" /><![endif]-->
<!--[if IE 7]><link rel="stylesheet" type="text/css" href="<?php echo $base_url.'/style/'.$forum_user['style'] ?>/Oxygen_ie7.css" /><![endif]-->
<?php
}
else
{
?>
<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $base_url.'/style/'.$forum_user['style'] ?>/Oxygen_admin.css" />
<link rel="stylesheet" type="text/css" media="screen" href="<?php echo $base_url.'/style/'.$forum_user['style'] ?>/Oxygen_admin_cs.css" />
<!--[if lte IE 6]><link rel="stylesheet" type="text/css" href="<?php echo $base_url.'/style/'.$forum_user['style'] ?>/Oxygen_admin_ie6.css" /><![endif]-->
<!--[if IE 7]><link rel="stylesheet" type="text/css" href="<?php echo $base_url.'/style/'.$forum_user['style'] ?>/Oxygen_admin_ie7.css" /><![endif]-->
<?php
}
?>