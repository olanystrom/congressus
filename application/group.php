<?php /*
    Copyright 2015-2020 Cédric Levieux, Parti Pirate

    This file is part of Congressus.

    Congressus is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Congressus is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Congressus.  If not, see <https://www.gnu.org/licenses/>.
*/
include_once("header.php");

require_once("engine/utils/Parsedown.php");
require_once("engine/utils/ParsedownExtra.php");
require_once("engine/emojione/autoload.php");

require_once("engine/bo/GaletteBo.php");

$groupFilters = array();

$galetteBo = GaletteBo::newInstance($connection, $config["galette"]["db"]);

$showAdmin = false;
$authoritatives = array();
$authorityAdmins = array();

if ($isAdmin && !isset($group)) {
	$group = array("gro_id" => 0, "gro_themes" => array(), "gro_label" => "Nouveau groupe", "gro_contact_type" => "none", "gro_contact" => "", "gro_tasker_type" => "", "gro_tasker_project_id" => "");
}

if ($isConnected) {
	if ($group["gro_id"]) {
		$isAdmin = $isAdmin || $groupBo->isMemberAdmin($group, $sessionUserId);
		$showAdmin = isset($_REQUEST["admin"]);

		if ($isAdmin) {
			$admins = $groupBo->getMemberAdmins($group);
			$authorityAdmins = $groupBo->getAuthorityAdmins($group);
			$authoritatives = $galetteBo->getGroups();
		}
	}
	else {
		$isAdmin = true;
		$showAdmin = true;
		$admins = array();
	}
}

$myVotingGroups = array();
$myEligibleGroups = array();
if ($sessionUserId) {
	$myVotingGroups = $groupBo->getMyGroups(array("userId" => $sessionUserId, "state" => "voting"));
	$myEligibleGroups = $groupBo->getMyGroups(array("userId" => $sessionUserId, "state" => "eligible"));
}

function isInMyGroup($theme, $mygroups) {
	foreach($mygroups as $group) {
		foreach($group["gro_themes"] as $myTheme) {
			if ($myTheme["the_id"] == $theme["the_id"]) return true;
		}
	}

	return false;
}

$Parsedown = new ParsedownExtra();
$emojiClient = new Emojione\Client(new Emojione\Ruleset());

?>

<div class="container group-container theme-showcase" role="main">
	<?=getBreadcrumb()?>

<?php	if(!$showAdmin && $group["gro_description"]) { ?>
	<div class="well well-sm">
		<?=$emojiClient->shortnameToImage($Parsedown->text($group["gro_description"]))?>
	</div>
<?php	} ?>


<?php
foreach($group["gro_themes"] as $themeId => $theme) {
	$class = "";
	$class .= isInMyGroup($theme, $myVotingGroups) ? "voting" : "no-voting";
	$class .= isInMyGroup($theme, $myEligibleGroups) ? " eligible" : " no-eligible";
?>

	<div class="panel panel-default theme <?php echo $class ?>">
		<div class="panel-heading">

		<?php if ($showAdmin) {?>
			<div class="pull-right" >
				<a href="theme.php?id=<?php echo $themeId; ?>&admin=" class="no-collapse btn btn-primary btn-xs theme-link">G&eacute;rer <i class="fa fa-cog"></i></a>
				<form id="exclude-<?php echo $themeId; ?>" action="do_exclude_theme_group.php" method="post" style="display: inline;">
					<input type="hidden" name="gth_group_id" value="<?php echo $group["gro_id"]; ?>" />
					<input type="hidden" name="gth_theme_id" value="<?php echo $themeId; ?>" />
					<button class="btn btn-danger btn-xs excludeButton">Exclure du groupe <span class="glyphicon glyphicon-remove"></span></button>
				</form>
			</div>
		<?php } ?>

			<a href="theme.php?id=<?php echo $themeId; ?>" class="theme-link no-collapse"><?php echo $theme["the_label"]; ?></a>&nbsp;
		</div>
		<div class="panel-body">


	<?php if ($showAdmin) {?>
		<form id="savePower-<?php echo $themeId; ?>" action="do_save_power_theme.php" method="post"  class="form-horizontal">
			<input type="hidden" name="gth_group_id" value="<?=$group["gro_id"]?>" />
			<input type="hidden" name="gth_theme_id" value="<?=$themeId?>" />

			<div class="form-group">
				<label class="col-md-3 control-label" for="gth_power_<?=$group["gro_id"]?>_<?=$themeId?>">Pouvoir de vote dans le groupe :</label>
				<div class="col-md-1">
					<input class="form-control input-md" type="number" name="gth_power" id="gth_power_<?=$group["gro_id"]?>_<?=$themeId?>" value="<?php echo $theme["gth_power"]; ?>" />
				</div>
			</div>
		</form>
	<?php }?>

	<?php if (!$showAdmin) {?>

	<?php
	if ($theme["the_delegate_only"] != "1") {
		foreach($theme["fixation"]["members"] as $memberId => $member) {
	?>
		<a href="member.php?id=<?php echo $memberId; ?>"><?php echo GaletteBo::showIdentity($member); ?></a><br/>
	<?php
		}
	}
	else {
		echo "Seulement de la délégation";
	}
	?>
<!--
		<br/>
		<button data-theme="<?php echo $themeId; ?>" class="delegate-button">Deleguer</button>
		<button data-theme="<?php echo $themeId; ?>" class="candidate-button">Candidater</button>
		<br/>
 -->
			<?php if ($theme["fixation"]["fix_until_date"]) { ?>
			<br/><span class="glyphicon glyphicon-time"></span> <span class="date"><?php echo $theme["fixation"]["fix_until_date"]; ?></span>
			<?php }?>

		<?php }?>

		</div>
	</div>
<?php
}
?>

<?php
	if (!$showAdmin) {
		$fixation = null;
		$principalEntryTheme = null;

		if ($group["gro_entry_theme_id"]) {
			foreach($group["gro_themes"] as $themeId => $theme) { 
				if ($themeId == $group["gro_entry_theme_id"]) {
					$principalEntryTheme = $theme;
					
					$isVoting = false;
					$isElegible = false;
					if ($isConnected) {
						$isVoting = count($groupBo->getMyGroups(array("the_id" => $themeId, "userId" => $sessionUserId, "state" => "voting"))) > 0;
						$isElegible = count($groupBo->getMyGroups(array("the_id" => $themeId, "userId" => $sessionUserId, "state" => "eligible"))) > 0;
					}

					$isFixed = false;

					$fixation = $principalEntryTheme["fixation"];

					if ($fixation) {
						foreach($fixation["members"] as $memberId => $member) {
							if ($memberId == $sessionUserId) {
								$isFixed = true;
								break;
							}
						}
					}

					break;
				}
			}
		}

		if ($fixation && !$isFixed && $isElegible && $principalEntryTheme["the_free_fixed"] == 1) {?>
	
<div class="row">
	<div class="col-md-12">
		<a href="#" id="free-theme-enter-btn" class="btn btn-default btn-lg btn-full-width" data-theme-id="<?php echo $principalEntryTheme["the_id"]; ?>"><?php echo lang("theme_free_entry"); ?></a>
	</div>
</div>
<br>

<?php	
		}
		else if ($fixation && $isFixed && $isElegible && $principalEntryTheme["the_free_fixed"] == 1) { ?>

<div class="row">
	<div class="col-md-12">
		<a href="#" id="free-theme-exit-btn" class="btn btn-default btn-lg btn-full-width" data-theme-id="<?php echo $principalEntryTheme["the_id"]; ?>"><?php echo lang("theme_free_exit"); ?></a>
	</div>
</div>
<br>

<?php	
		}
		else if (!$principalEntryTheme["the_free_fixed"] && $principalEntryTheme["the_voting_method"] == "external_results" && $principalEntryTheme["the_entry_condition"]) { ?>
	<div class="well well-sm">
		<?=$emojiClient->shortnameToImage($Parsedown->text($principalEntryTheme["the_entry_condition"]))?>
	</div>

<?php	
		}
	}
?>






















<?php if ($isAdmin && $showAdmin) {?>

	<div id="information">
		<div class="panel panel-success">
			<div class="panel-heading">
				Information&nbsp;
			</div>
			<div class="panel-body">

				<form id="saveGroupForm" action="do_save_group.php" method="post" class="form-horizontal">
					<fieldset>
						<input type="hidden" name="gro_id" id="gro_id" value="<?=$group["gro_id"]?>" />

						<?php // echo print_r($group, true); ?>

						<div class="form-group">
							<label class="col-md-3 control-label" for="gro_label">Libellé : </label>
							<div class="col-md-3">
								<input type="text" name="gro_label" id="gro_label"
									placeholder="" class="form-control input-md"
									value="<?=$group["gro_label"]?>"/>
							</div>
						</div>

						<div class="form-group">
							<label class="col-md-3 control-label" for="gro_description">Description : </label>
							<div class="col-md-9">                     
								<textarea class="form-control" id="gro_description" name="gro_description" data-provide="markdown" data-hidden-buttons="cmdPreview"><?=$group["gro_description"]?></textarea>
							</div>
						</div>

						<div class="form-group">
							<label class="col-md-3 control-label" for="gro_contact_type">Type de contact : </label>
							<div class="col-md-3">
								<select name="gro_contact_type" id="gro_contact_type" class="form-control input-md">
									<option value="none" <?php echo $group["gro_contact_type"] == "none" ? 'selected="selected"' : ''; ?>>Aucun</option>
									<option value="mail" <?php echo $group["gro_contact_type"] == "mail" ? 'selected="selected"' : ''; ?>>Par mail</option>
									<option value="discourse_group" <?php echo $group["gro_contact_type"] == "discourse_group" ? 'selected="selected"' : ''; ?>>Groupe Discourse</option>
									<option value="discourse_category" <?php echo $group["gro_contact_type"] == "discourse_category" ? 'selected="selected"' : ''; ?>>Catégorie Discourse</option>
								</select>
							</div>			

							<label class="col-md-3 control-label contact-type mail" for="gro_contact">Adresse mail : </label>
							<label class="col-md-3 control-label contact-type discourse_group" for="gro_contact">Groupe Discourse : </label>
							<label class="col-md-3 control-label contact-type discourse_category" for="gro_contact">Categorie Discourse : </label>
							<div class="col-md-3 contact-type mail discourse_group discourse_category">
								<input type="text" name="gro_contact" id="gro_contact"
									placeholder="" class="form-control input-md"
									value="<?php echo $group["gro_contact"]; ?>"/>
							</div>
						</div>

						<div class="form-group">
							<label class="col-md-3 control-label" for="gro_entry_theme_id">Thème principal : </label>
							<div class="col-md-3">
								<select name="gro_entry_theme_id" id="gro_entry_theme_id" class="form-control input-md">
									<option value="0"></option>
<?php	foreach($group["gro_themes"] as $themeId => $theme) { ?>
									<option value="<?=$theme["the_id"]?>" <?=($theme["the_id"] == $group["gro_entry_theme_id"] ? "selected=selected" : "")?>><?=$theme["the_label"]?></option>
<?php	} ?>
								</select>
							</div>			

					</fieldset>

				</form>
			</div>
		</div>
	</div>

	<div id="authoritative" <?php if (!$group["gro_id"]) {?>style="display: none;"<?php } ?>>
		<div class="panel panel-success">
			<div class="panel-heading">
				A autorité sur&nbsp;
			</div>
			<div class="panel-body">

				<form id="addAuthoritativeForm" action="do_set_group_authority.php" method="post" class="form-horizontal">
					<fieldset>
						<input type="hidden" name="action" value="add_authority" />
						<input type="hidden" name="gau_group_id" id="gau_group_id" value="<?=$group["gro_id"]?>" />

						<label class="col-md-3 control-label" for="gau_authoritative_id">Groupe d'utilisateurs à ajouter : </label>
						<div class="col-md-7">
							<select name="gau_authoritative_id" id="gau_authoritative_id" class="form-control input-md">
								<option value=""></option>
								<option value="0">Tous les membres</option>
							<?php 	foreach($authoritatives as $authoritative) { ?>
								<option value="<?php echo $authoritative["id_group"]; ?>"><?php echo utf8_encode($authoritative["group_name"]); ?></option>
							<?php	} ?>
							</select>
						</div>
						<div class="col-md-2">
							<button id="addAuthoritativeButton" class="btn btn-primary">Ajouter <span class="glyphicon glyphicon-plus"></span></button>
						</div>

					</fieldset>

				</form>

				<table>
					<tbody>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div id="tasks" <?php if (!$group["gro_id"]) {?>style="display: none;"<?php } ?>>
		<div class="panel panel-success">
			<div class="panel-heading">
				Gestion des tâches&nbsp;
			</div>
			<div class="panel-body">

				<form id="saveTaskForm" action="do_save_group.php" method="post" class="form-horizontal">
					<fieldset>
						<input type="hidden" name="gro_id" id="task_gro_id" value="<?=$group["gro_id"]?>" />

						<div class="form-group">

							<label class="col-md-3 control-label" for="gro_tasker_type">Gestionnaire de tâches : </label>
							<div class="col-md-3">
								<select name="gro_tasker_type" id="gro_tasker_type" class="form-control input-md">
									<option value="none"></option>
<?php
		$directoryHandler = dir("task_hooks/");

		while(($fileEntry = $directoryHandler->read()) !== false) {
			if($fileEntry != '.' && $fileEntry != '..' && strpos($fileEntry, ".php")) {
				require_once("task_hooks/" . $fileEntry);
			}
		}
		$directoryHandler->close();
		
		$types = array("none");
		
		foreach($taskHooks as $taskHook) {
			$taskHookType = $taskHook->getType();
			$types[] = $taskHookType;
?>
									<option value="<?=$taskHookType?>" <?=(($taskHook->getType() == $group["gro_tasker_type"]) ? "selected='selected'" : "")?>><?=$taskHook->getType()?></option>
<?php
		}
?>
								</select>
							</div>

							<label class="col-md-3 control-label" for="gro_tasker_project_id">Projet : </label>
							<div class="col-md-3">
								<select name="gro_tasker_project_id" id="gro_tasker_project_id" class="form-control input-md">
									<option class="<?=implode(" ", $types)?>" value=""></option>

<?php
		foreach($taskHooks as $taskHook) {
			$projects = $taskHook->getProjects();

			foreach($projects as $project) {
?>
									<option class="<?=$taskHookType?>" value="<?=$project["id"]?>" <?=(($project["id"] == $group["gro_tasker_project_id"]) ? "selected='selected'" : "")?>><?=$project["name"]?></option>
<?php
			}
		}
?>
								</select>
							</div>

						</div>

					</fieldset>

				</form>
			</div>
		</div>
	</div>



	<div id="admins" <?php if (!$group["gro_id"]) {?>style="display: none;"<?php } ?>>
		<div class="panel panel-primary theme <?php echo $class ?>">
			<div class="panel-heading">
				Administrateurs du groupe&nbsp;
			</div>
			<div class="panel-body">

				<form id="addAdminForm" action="do_set_group_admin.php" method="post" class="form-horizontal">
					<fieldset>
						<input type="hidden" name="action" value="add_admin" />
						<input type="hidden" name="gad_group_id" value="<?php echo $group["gro_id"]; ?>" />

						<label class="col-md-4 control-label" for="gad_member_mail">Utilisateur à ajouter en tant qu'administrateur : </label>
						<div class="col-md-6">
							<div class="input-group">
								<input type="text" name="gad_member_mail" placeholder="email ou pseudo"
									class="form-control"
								/><span class="input-group-btn"><button
									data-success-function="addGroupAdminFromSearchForm"
									data-success-label="Ajouter"
									class="btn btn-default search-user"><span class="fa fa-search"></span></button></span>
							</div>
						</div>
						<div class="col-md-2">
							<button id="addAdminButton" class="btn btn-primary">Ajouter <span class="glyphicon glyphicon-plus"></span></button>
						</div>

					</fieldset>

				</form>

				<table>
					<tbody>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div style="text-align: center;">
		<button data-group-id="<?php echo $group["gro_id"]; ?>" class="btn btn-danger btn-delete-group"><?=lang("group_admin_remove_button")?> <i class="fa fa-remove"></i></button>
		<button data-group-id="<?php echo $group["gro_id"]; ?>" class="btn btn-primary addThemeButton"><?=lang("group_admin_add_theme_button")?> <i class="fa fa-plus"></i></button>
		

		<style>
			@media (min-width: 1024px) {
				#delete-group-modal .modal-dialog {
					width: 900px;
				}
			}
			
			@media (min-width: 1600px) {
				#delete-group-modal .modal-dialog {
					width: 1300px;
				}
			}
		</style>
		
		<div class="modal fade" tabindex="-1" role="dialog" id="delete-group-modal">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="<?=lang("common_close")?>"><span aria-hidden="true">&times;</span></button>
						<h4 class="modal-title"><?=lang("group_admin_remove_dialog_title")?></h4>
					</div>
					<div class="modal-body">

						<form class="form-horizontal" action="do_delete_group.php">
							<input type="hidden" name="gro_id" value="<?php echo $group["gro_id"]; ?>">
							<fieldset>
							</fieldset>
						</form>          

					</div>
					<div class="modal-footer">

						<button type="button" class="btn btn-default" data-dismiss="modal"><?=lang("common_close")?></button>
						<button type="button" class="btn btn-danger btn-confirm-delete-modal"><span class="glyphicon glyphicon-trash"></span> <?=lang("group_admin_remove")?></button>

					</div>
				</div><!-- /.modal-content -->
			</div><!-- /.modal-dialog -->
		</div><!-- /.modal -->

	</div>
<?php }?>

<?php include("connect_button.php"); ?>

<templates>
	<table>
		<tr data-template-id="template-group-admin" class="template">
			<td>${gad_member_identity}</td>
			<td>&nbsp;<a href="#" class="removeAdminLink text-danger" data-group-id="${gad_group_id}" data-member-id="${gad_member_id}"><i class="fa fa-remove"></i></td>
		</tr>
		<tr data-template-id="template-group-authoritative" class="template">
			<td>${gau_authoritative_name}</td>
			<td>&nbsp;<a href="#" class="removeAuthoritativeLink text-danger" data-group-id="${gau_group_id}" data-authoritative-id="${gau_authoritative_id}"><i class="fa fa-remove"></i></td>
		</tr>
	</table>
</templates>

<script>
var groupAdmins = [];
var groupAuthoritatives = [];

<?php 	foreach($admins as $admin) {?>
groupAdmins[groupAdmins.length] = {	gad_member_identity: "<?php echo GaletteBo::showIdentity($admin); ?>",
									gad_group_id : <?php echo $admin["gad_group_id"]; ?>,
									gad_member_id : <?php echo $admin["gad_member_id"]; ?>
								};
<?php 	}?>

<?php 	foreach($authorityAdmins as $admin) {?>
groupAuthoritatives[groupAuthoritatives.length] = {	gau_authoritative_name: "<?php echo utf8_encode($admin["gau_authoritative_name"] ? $admin["gau_authoritative_name"] : "Tous les membres"); ?>",
									gau_group_id : <?php echo $admin["gau_group_id"]; ?>,
									gau_authoritative_id : <?php echo $admin["gau_authoritative_id"]; ?>
								};
<?php 	}?>



</script>

</div>


<div class="container soft-hidden alert-container">
	<?php echo addAlertDialog("success_group_groupAlert", lang("success_group_group"), "success"); ?>
</div>

<div class="lastDiv"></div>


<?php include("footer.php");?>

<script src="assets/js/perpage/theme_entry.js?r=<?=filemtime("assets/js/perpage/theme_entry.js")?>"></script>

</body>
</html>