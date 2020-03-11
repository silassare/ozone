<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	defined('OZ_SELF_SECURITY_CHECK') or die;

	return [
		// ---------START NEW
		"OZ_ERROR_REQUEST_FAIL"      => "Erreur: la requête a échoué.",
		"OZ_ERROR_NETWORK"           => "Erreur: problèmes de connexion internet.",
		"OZ_ERROR_SERVER"            => "Erreur: echec de récupération des informations.",
		"OZ_ERROR_YOU_ARE_NOT_ADMIN" => "Erreur: Vous n'etes pas un administrateur.",

		"OZ_ERROR_BAD_REQUEST"         => "Votre requête est invalide.",
		"OZ_ERROR_INTERNAL"            => "Une erreur interne s'est produite.",
		"OZ_ERROR_RUNTIME"             => "Une erreur interne s'est produite en cours d'exécution.",
		"OZ_ERROR_METHOD_NOT_ALLOWED"  => "La méthode de la requête n'est pas autorisée.",
		"OZ_ERROR_NOT_FOUND"           => "La ressource recherchée n'est pas retrouvée.",
		"OZ_ERROR_INVALID_FORM"        => "La requête est invalide. Vous n'êtes peut-être pas autorisé à effectuer cette action.",
		"OZ_ERROR_YOU_MUST_LOGIN"      => "Vous devez vous connecter d'abord.",
		"OZ_ERROR_NOT_ALLOWED"         => "Une erreur s'est produite. Vous n'êtes peut-être pas autorisé à effectuer cette action.",
		"OZ_MISSING_API_KEY"           => "Vous devez fournir une clé API.",
		"OZ_YOUR_API_KEY_IS_NOT_VALID" => "La clef API est invalide.",

		"OZ_SMS_AUTH_CODE_MESSAGE"               => "Votre code d'authentification est: {code}",
		"OZ_SMS_AUTH_CODE_PASSWORD_EDIT_MESSAGE" => "Votre code d'authentification est: {code}",
		// ---------END NEW

		"OZ_IMAGE_NOT_VALID"            => "Fichier image invalide. Veuillez choisir une image de type png, jpeg, ou gif.",
		"OZ_PROFILE_PIC_SET_TO_DEFAULT" => "Photo de profil par défaut choisie.",
		"OZ_PROFILE_PIC_CHANGED"        => "Photo de profil changer.",
		"OZ_FORM_CONTAINS_EMPTY_FIELD"  => "Le champ `{label}` est vide.",
		"OZ_FILE_TOO_BIG"               => "Fichier trop lourd, maximum 100Mb.",
		"OZ_FILE_IS_EMPTY"              => "Fichier vide.",

		"OZ_FIELD_PHONE_ALREADY_REGISTERED" => "Le {phone} est déjà associé à un autre compte.",
		"OZ_FIELD_EMAIL_ALREADY_REGISTERED" => "L'adresse mail {email} est déjà associé à un autre compte.",
		"OZ_FIELD_PHONE_INVALID"            => "Le numéro est invalide.",
		"OZ_FIELD_PHONE_NOT_REGISTERED"     => "Ce numéro n'est pas inscrit.",
		"OZ_FIELD_EMAIL_NOT_REGISTERED"     => "Cette adresse mail n'est pas enrégistrée.",
		"OZ_FIELD_PASS_INVALID"             => "Le mot de passe est incorrect.",
		"OZ_FIELD_COUNTRY_NOT_ALLOWED"      => "Le pays spécifié n'est pas valide. Le service n'est peut-être pas encore dans votre pays.",
		"OZ_AUTH_CODE_SENT"                 => "Un code vous a été envoyé au: {phone}",
		"OZ_AUTH_CODE_NEW_SENT"             => "Un nouveau code vous a été envoyé au: {phone}",
		"OZ_AUTH_CODE_OK"                   => "Code de verification correct!",
		"OZ_AUTH_CODE_INVALID"              => "Le code n'est pas valide.",
		"OZ_AUTH_CODE_EXCEED_MAX_FAIL"      => "Vous avez atteint le nombre maximum d'échecs autorisés pour le même code.",
		"OZ_AUTH_CODE_EXPIRED"              => "Le code a déjà expiré.",
		"OZ_AUTH_PROCESS_INVALID"           => "Veuillez recommencer le processus d'authentification",

		"OZ_PHONE_AUTH_NOT_STARTED"   => "Vous n'avez pas validé l'étape 1.",
		"OZ_PHONE_AUTH_NOT_VALIDATED" => "Vous n'avez pas validé l'étape 2.",

		"OZ_FIELD_USER_NAME_INVALID"           => "Le nom d'utilisateur contient des caractères non autorisés.",
		"OZ_FIELD_USER_NAME_TOO_SHORT"         => "Le nom d'utilisateur est trop court.",
		"OZ_FIELD_USER_NAME_TOO_LONG"          => "Le nom d'utilisateur est trop long.",
		"OZ_FIELD_USER_NAME_CONTAINS_KEYWORDS" => "Le nom d'utilisateur ne doit pas contenir des mots clés...",

		"OZ_FIELD_EMAIL_INVALID"            => "L'adresse mail n'est pas valide.",
		"OZ_FIELD_GENDER_INVALID"           => "Veuillez indiquer votre sexe.",
		"OZ_FIELD_BIRTH_DATE_INVALID"       => "La date de naissance n'est pas valide ({min} ans minimum et {max} ans maximum).",
		"OZ_FIELD_PASS_AND_VPASS_NOT_EQUAL" => "Le mot de passe et le mot de passe de vérification doivent être les mêmes.",
		"OZ_FIELD_PASS_TOO_LONG"            => "Le mot de passe est trop long.",
		"OZ_FIELD_PASS_TOO_SHORT"           => "Le mot de passe est trop court.",
		"OZ_SIGNUP_SUCCESS"                 => "Inscription Réussie.",

		"OZ_USER_ONLINE"            => "Vous êtes connecté.",
		"OZ_USER_LOGOUT"            => "Vous vous êtes déconnecté.",
		"OZ_LOGOUT_FAIL"            => "La déconnexion a échoué.",
		"OZ_FILE_UPLOAD_FAIL"       => "Échec de l'envoie du ou des fichiers",
		"OZ_FILE_ALIAS_UNKNOWN"     => "Fichier alias inconu.",
		"OZ_FILE_ALIAS_PARSE_ERROR" => "Fichier alias, erreur d'analyse.",
		"OZ_FILE_ALIAS_NOT_FOUND"   => "Le fichier alias ou le fichier ciblé est introuvable...",

		"OZ_ERROR_GO_HOME_BTN" => "Accueil",
		"OZ_ERROR_GO_BACK_BTN" => "Retour",
		"OZ_REDIRECT_TITLE"    => "Vous allez être rediriger.",
		"OZ_REDIRECT_MESSAGE"  => "Si vous n'êtes pas redirigé automatiquement, suivez ce <a href=\"{url}\">lien</a>.",
	];