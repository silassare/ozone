<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

return [
	'OZ_ERROR_YOU_ARE_NOT_ADMIN'        => "Erreur: Vous n'etes pas un administrateur.",
	'OZ_ERROR_BAD_REQUEST'              => 'Votre requête est invalide.',
	'OZ_ERROR_INTERNAL'                 => "Une erreur interne s'est produite.",
	'OZ_ERROR_RUNTIME'                  => "Une erreur interne s'est produite en cours d'exécution.",
	'OZ_ERROR_NOT_FOUND'                => "La ressource recherchée n'est pas retrouvée.",
	'OZ_ERROR_METHOD_NOT_ALLOWED'       => "La méthode de la requête n'est pas autorisée.",
	'OZ_ERROR_NOT_ALLOWED'              => "Une erreur s'est produite. Vous n'êtes peut-être pas autorisé à effectuer cette action.",
	'OZ_ERROR_INVALID_FORM'             => "La requête est invalide. Vous n'êtes peut-être pas autorisé à effectuer cette action.",
	'OZ_ERROR_YOU_MUST_LOGIN'           => "Vous devez vous connecter d'abord.",
	'OZ_ERROR_RATE_LIMIT_REACHED'       => 'Vous avez atteint la limite de requêtes.',
	'OZ_MISSING_API_KEY'                => 'Vous devez fournir une clé API.',
	'OZ_YOUR_API_KEY_IS_NOT_VALID'      => 'La clef API est invalide.',
	'OZ_IMAGE_NOT_VALID'                => 'Fichier image invalide. Veuillez choisir une image de type png, jpeg, ou gif.',
	'OZ_PROFILE_PIC_SET_TO_DEFAULT'     => 'Photo de profil par défaut choisie.',
	'OZ_PROFILE_PIC_UPDATED'            => 'Photo de profil mise à jour.',
	'OZ_FIELD_PHONE_ALREADY_REGISTERED' => 'Le {phone} est déjà associé à un autre compte.',
	'OZ_FIELD_EMAIL_ALREADY_REGISTERED' => "L'adresse mail {email} est déjà associé à un autre compte.",
	'OZ_FIELD_PHONE_INVALID'            => 'Le numéro est invalide.',
	'OZ_FIELD_PHONE_NOT_REGISTERED'     => "Ce numéro n'est pas inscrit.",
	'OZ_FIELD_EMAIL_NOT_REGISTERED'     => "Cette adresse mail n'est pas enrégistrée.",
	'OZ_FIELD_COUNTRY_NOT_ALLOWED'      => "Le pays spécifié n'est pas valide. Le service n'est peut-être pas encore dans votre pays.",
	'OZ_FIELD_USER_NAME_INVALID'        => "Le nom d'utilisateur contient des caractères non autorisés.",
	'OZ_FIELD_USER_NAME_TOO_SHORT'      => "Le nom d'utilisateur est trop court.",
	'OZ_FIELD_USER_NAME_TOO_LONG'       => "Le nom d'utilisateur est trop long.",
	'OZ_FIELD_EMAIL_INVALID'            => "L'adresse mail n'est pas valide.",
	'OZ_FIELD_GENDER_INVALID'           => 'Le genre est invalide.',
	'OZ_FIELD_PASS_INVALID'             => 'Le mot de passe est incorrect.',
	'OZ_FIELD_PASS_NOT_SECURE'          => 'Le mot de passe n’est pas assez sécurisé.',
	'OZ_FIELD_PASS_TOO_LONG'            => 'Le mot de passe est trop long.',
	'OZ_FIELD_PASS_TOO_SHORT'           => 'Le mot de passe est trop court.',
	'OZ_FIELD_SHOULD_HAVE_SAME_VALUE'   => 'Les champs {field} et {field_confirm} doivent avoir la même valeur.',
	'OZ_USER_SIGN_UP_SUCCESS'           => 'Inscription Réussie.',
	'OZ_USER_SIGN_IN_DONE'              => 'Vous êtes connecté.',
	'OZ_USER_LOGOUT_DONE'               => 'Vous vous êtes déconnecté.',
	'OZ_FILE_UPLOAD_FAIL'               => "Échec de l'envoie du ou des fichiers",

	// used in views templates
	'OZ_VIEW_GO_HOME_BTN'               => 'Accueil',
	'OZ_VIEW_GO_BACK_BTN'               => 'Retour',
	'OZ_VIEW_REDIRECT_TITLE'            => 'Vous allez être rediriger.',
	'OZ_VIEW_REDIRECT_MESSAGE'          => "Si vous n'êtes pas redirigé automatiquement, suivez ce <a href=\"{url}\">lien</a>.",
];
