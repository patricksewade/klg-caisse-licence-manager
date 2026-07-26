<?php
	date_default_timezone_set('Africa/Porto-Novo');
	require_once './config.php';
?>
<?php
	if(isset($_GET['action']) and !empty($_GET['action']))
	{
		switch($_GET['action'])
		{
			case 'get-data-licence':
			
				if(isset($_GET['cle_licence']) and !empty($_GET['cle_licence']))
				{
					$cle_licence = secureDataIn($_GET['cle_licence']);
					
					$sql = 'SELECT * FROM cles_licence WHERE cle_licence = "'.$cle_licence.'" ';
					$resql = mysqli_query($connectBdd, $sql);
					$nbreRows = mysqli_num_rows($resql);
					
					if($nbreRows > 0)
					{
						$dataLicence = mysqli_fetch_object($resql);
						
						$array_data_licence['licence_exists'] = TRUE;
						$array_data_licence['nom_prenom_titulaire_licence'] = $dataLicence->nom_prenom_titulaire_licence;
						$array_data_licence['telephone_titulaire_licence'] = $dataLicence->telephone_titulaire_licence;
						$array_data_licence['email_titulaire_licence'] = $dataLicence->email_titulaire_licence;
						$array_data_licence['type_licence'] = $dataLicence->type_licence;
						$array_data_licence['duree_periode_utilisation'] = $dataLicence->duree_periode_utilisation;
						$array_data_licence['unite_temps_periode_utilisation'] = $dataLicence->unite_temps_periode_utilisation;
						$array_data_licence['cle_licence'] = $dataLicence->cle_licence;
						$array_data_licence['licence_is_currently_used'] = $dataLicence->licence_is_currently_used;
						$array_data_licence['licence_is_locked'] = $dataLicence->licence_is_locked;
						$array_data_licence['nom_serveur'] = $dataLicence->nom_serveur;
						$array_data_licence['date_first_activation_licence'] = $dataLicence->date_first_activation_licence;
						$array_data_licence['date_last_activation_licence'] = $dataLicence->date_last_activation_licence;
						$array_data_licence['date_expiration_licence'] = $dataLicence->date_expiration_licence;
					}
					else
					{
						$array_data_licence['licence_exists'] = FALSE;
					}
					
					$dataToReturn = json_encode($array_data_licence);
					exit($dataToReturn);
				}
				
			break;
			
			case 'update-licence':
			
				if(isset($_GET['data_licence']) and !empty($_GET['data_licence']))
				{
					$current_datetime = getCurrentDateTime();
					
					//var_dump($_GET['data_licence']);
					$data_licence = json_decode($_GET['data_licence']);
					//var_dump($data_licence);
					
					$sql = 'UPDATE cles_licence SET nom_serveur = "'.$data_licence->nom_serveur.'",
													licence_is_currently_used = "'.$data_licence->licence_is_currently_used.'",
													licence_is_locked = "'.$data_licence->licence_is_locked.'",
													date_first_activation_licence = "'.$data_licence->date_first_activation_licence.'",
													date_last_activation_licence = "'.$data_licence->date_last_activation_licence.'",
													date_expiration_licence = "'.$data_licence->date_expiration_licence.'",
													date_modif = "'.$current_datetime.'" WHERE cle_licence = "'.$data_licence->cle_licence.'"';
					
					$modifInfosBDD = mysqli_query($connectBdd, $sql);
					
					if($modifInfosBDD)
					{
						$array_data_licence['data_licence_updated_via_api'] = TRUE;
					}
					else
					{
						$array_data_licence['data_licence_updated_via_api'] = FALSE;
					}
					
					$dataToReturn = json_encode($array_data_licence);
					exit($dataToReturn);
				}
				
			break;			
				
			case 'desactiver-licence':
			
				if(isset($_GET['cle_licence']) and !empty($_GET['cle_licence']))
				{
					$cle_licence = secureDataIn($_GET['cle_licence']);
					$current_datetime = getCurrentDateTime();
					
					$sql = 'UPDATE cles_licence SET licence_is_currently_used = 0,
													date_modif = "'.$current_datetime.'" WHERE cle_licence = "'.$cle_licence.'"';
					
					$modifInfosBDD = mysqli_query($connectBdd, $sql);
					
					if($modifInfosBDD)
					{
						$array_data_licence['licence_desactive_via_api'] = TRUE;
					}
					else
					{
						$array_data_licence['licence_desactive_via_api'] = FALSE;
					}
					
					$dataToReturn = json_encode($array_data_licence);
					exit($dataToReturn);
				}
			break;
			
			case 'get-latest-app-version':
			
				$array_app_versions = array('1.0.0',
											'1.0.1',
											'1.0.2',
											'1.0.3',
											'1.0.4',
											'1.0.5',
											'1.0.6'
											);
											
				$array_data_api['all_versions'] = $array_app_versions;
				$array_data_api['latest_version'] = end($array_app_versions);
				$dataToReturn = json_encode($array_data_api);
				
				exit($dataToReturn);
				
			break;
		}
	}
	else
	{
		?>
			<!DOCTYPE html>
			<html lang="fr">
				<head>
					<meta charset="UTF-8">
					<title>KLG-Caisse | Gestion des licences</title>
					<meta name="robots" content="noindex, nofollow">
				</head>
				<body class="body-container">
					<?php
						if(isset($_POST['btnEnvoyer']))
						{
							/*var_dump($_POST);
							exit;*/
							
							$nom_prenom_titulaire_licence = secureDataIn($_POST['nom_prenom_titulaire_licence']);
							$telephone_titulaire_licence = secureDataIn($_POST['telephone_titulaire_licence']);
							$email_titulaire_licence = secureDataIn($_POST['email_titulaire_licence']);
							$type_licence = secureDataIn($_POST['type_licence']);
							$duree_periode_utilisation = secureDataIn($_POST['duree_periode_utilisation']);
							$unite_temps_periode_utilisation = secureDataIn($_POST['unite_temps_periode_utilisation']);
							
							$cle_licence = generer_cle_licence_klg_caisse();
							
							$currentDateTime = getCurrentDateTime();
							
							$saveInDataBase = mysqli_query($connectBdd, 'INSERT INTO cles_licence (nom_prenom_titulaire_licence,
																								   telephone_titulaire_licence,
																								   email_titulaire_licence,
																								   type_licence,
																								   duree_periode_utilisation,
																								   unite_temps_periode_utilisation,
																								   cle_licence,
																								   date_create) VALUES ("'.$nom_prenom_titulaire_licence.'",
																														  "'.$telephone_titulaire_licence.'",
																														  "'.$email_titulaire_licence.'",
																														  "'.$type_licence.'",
																														  "'.$duree_periode_utilisation.'",
																														  "'.$unite_temps_periode_utilisation.'",
																														  "'.$cle_licence.'",
																														  "'.$currentDateTime.'"
																														  )');
							if($saveInDataBase)
							{
								echo '<p style="text-align: center;">La clé de licence générée est : <span style="color:green;font-weight: bold;">'.$cle_licence.'</span></p>';
							}
							else
							{
								echo '<p class="error">Une erreur s\'est produite lors de l\'enregistrement des informations dans la base de données : <br>'.utf8_encode(mysqli_error($connectBdd)).'</p>';
							}
						}
					?>
					<form method="post" action="" style="max-width: 540px;width: 100%;margin: auto;margin-bottom: 50px;" autocomplete="off">
						<fieldset style="padding: 15px 0;background-color: #fff;">
							<legend align="center"><i class="fas fa-database"></i> Formulaire de génération de licence : KLG-Caisse</legend>
							<div class="formulaire" style="max-width: 470px;width: 100%;margin: auto;">
								<div class="formGroup">
									<label for="nom_prenom_titulaire_licence"><i class="fas fa-store-alt"></i> Nom et prénom du titulaire de la licence <span class="colorRed">*</span> : </label>
									<input type="text" id="nom_prenom_titulaire_licence" name="nom_prenom_titulaire_licence">
								</div>
								<div class="formGroup">
									<label for="telephone_titulaire_licence"><i class="fas fa-store-alt"></i> Téléphone du titulaire de la licence <span class="colorRed">*</span> : </label>
									<input type="text" id="telephone_titulaire_licence" name="telephone_titulaire_licence">
								</div>
								<div class="formGroup">
									<label for="email_titulaire_licence"><i class="fas fa-store-alt"></i> E-mail du titulaire de la licence : </label>
									<input type="email" id="email_titulaire_licence" name="email_titulaire_licence">
								</div>
								<div class="formGroup">
									<label for="type_licence"><i class="fas fa-key"></i> Type de licence souhaité <span class="colorRed">*</span> : </label>
									<select name="type_licence" data-placeholder="Sélectionnez un type de licence" required>
										<option value="ESSAI">ESSAI</option>
										<option value="ABONNEMENT">ABONNEMENT</option>
										<option value="PERPETUELLE">PERPETUELLE</option>
									</select>
								</div>
								<div class="formGroup">
									<label for="duree_periode_utilisation"><i class="fas fa-store-alt"></i> Période d'utilisation <span class="colorRed">*</span> : </label>
									<input type="number" id="duree_periode_utilisation" min=1 name="duree_periode_utilisation">
									<select name="unite_temps_periode_utilisation" data-placeholder="Sélectionnez une unité de temps">
										<option value="years">Année(s)</option>
										<option value="months">Mois</option>
										<option value="days">Jour(s)</option>
										<option value="hours">Heure(s)</option>
										<option value="minutes">Minute(s)</option>
										<option value="seconds">Seconde(s)</option>
									</select>
								</div>
								<div class="formGroup" style="margin-top: 25px; text-align:center;">
									<button type="submit" name="btnEnvoyer" class="btn fullWidth">
										<i class="fas fa-floppy-disk-circle-arrow-right" style="color: white;"></i> GÉNÉRER LA LICENCE
									</button>
								</div>
							</div>
						</fieldset>	
					</form>
				</body>
			</html>
		<?php
	}
?>