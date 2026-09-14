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
	// generic errors
	'OZ_ERROR_BAD_REQUEST'                   => 'Votre requête est invalide.',
	'OZ_ERROR_INTERNAL'                      => "Une erreur interne s'est produite.",
	'OZ_ERROR_RUNTIME'                       => "Une erreur interne s'est produite en cours d'exécution.",
	'OZ_ERROR_NOT_FOUND'                     => "La ressource recherchée n'est pas retrouvée.",
	'OZ_ERROR_METHOD_NOT_ALLOWED'            => "La méthode de la requête n'est pas autorisée.",
	'OZ_ERROR_NOT_ALLOWED'                   => "Une erreur s'est produite. Vous n'êtes peut-être pas autorisé à effectuer cette action.",
	'OZ_ERROR_INVALID_FORM'                  => 'Les données envoyées sont invalides.',
	'OZ_ERROR_UNAUTHENTICATED'               => "Vous devez vous connecter d'abord.",
	'OZ_ERROR_RATE_LIMIT_REACHED'            => 'Vous avez atteint la limite de requêtes.',
	'OZ_RATE_LIMIT_EXCEEDED'                 => 'Trop de requêtes. Veuillez réessayer plus tard.',
	'OZ_RECURSIVE_REDIRECTION'               => 'Trop de redirections.',
	'OZ_CROSS_SITE_REQUEST_NOT_ALLOWED'      => "Requête d'une origine non autorisée.",
	'OZ_CSRF_TOKEN_INVALID'                  => 'Le jeton de sécurité est manquant, invalide ou expiré. Rechargez la page puis réessayez.',

	// roles and access rights
	'OZ_ERROR_YOU_ARE_NOT_ADMIN'             => "Vous n'êtes pas un administrateur.",
	'OZ_ERROR_YOU_ARE_NOT_EDITOR'            => "Vous n'êtes pas un éditeur.",
	'OZ_ERROR_YOU_ARE_NOT_SUPER_ADMIN'       => "Vous n'êtes pas un super administrateur.",
	'OZ_ERROR_USER_IS_MISSING_REQUIRED_ROLE' => "Vous n'avez pas le rôle requis pour cette action.",
	'OZ_ERROR_USER_TYPE_NOT_ALLOWED'         => "Votre type de compte n'est pas autorisé ici.",
	'OZ_ERROR_MISSING_ACCESS_RIGHTS'         => "Vous n'avez pas les droits d'accès requis pour cette action.",
	'OZ_ACCESS_RIGHT_DESCRIPTION'            => "Autorise l'action {action}.",
	'OZ_MISSING_ACCESS_RIGHT'                => "Vous n'êtes pas autorisé à effectuer l'action {action}.",

	// API keys and authorizations
	'OZ_MISSING_API_KEY'                     => 'Vous devez fournir une clé API.',
	'OZ_YOUR_API_KEY_IS_NOT_VALID'           => 'La clé API est invalide.',
	'OZ_AUTH_AUTHORIZED'                     => 'Autorisation accordée.',
	'OZ_AUTH_REFRESHED'                      => "L'autorisation a été renouvelée.",
	'OZ_AUTH_DISABLED'                       => 'Cette autorisation a été désactivée.',
	'OZ_AUTH_EXPIRED'                        => 'Cette autorisation a expiré.',
	'OZ_AUTH_HAS_EXPIRED'                    => 'Cette autorisation a expiré.',
	'OZ_AUTH_NOT_FOUND'                      => 'Autorisation introuvable.',
	'OZ_AUTH_INVALID_OR_DELETED_REF'         => "Cette autorisation n'existe pas ou a été supprimée.",
	'OZ_AUTH_MISSING_REF'                    => "La référence de l'autorisation est manquante.",
	'OZ_AUTH_REF_NOT_PROVIDED'               => "La référence de l'autorisation n'a pas été fournie.",
	'OZ_AUTH_MISSING_SECRET'                 => 'Le code ou le jeton est manquant.',
	'OZ_AUTH_CODE_INVALID'                   => 'Le code est invalide ou a déjà été utilisé.',
	'OZ_AUTH_INVALID_CODE'                   => 'Le code est invalide.',
	'OZ_AUTH_INVALID_TOKEN'                  => 'Le jeton est invalide.',
	'OZ_AUTH_INVALID_REFRESH_KEY'            => 'La clé de rafraîchissement est invalide.',
	'OZ_AUTH_REFRESH_KEY_INVALID'            => 'La clé de rafraîchissement est invalide ou a expiré.',
	'OZ_AUTH_INVALID_PROVIDER'               => 'Ces identifiants ne permettent pas de se connecter.',
	'OZ_AUTH_NOT_AUTHORIZED'                 => "Ces identifiants n'ont pas encore été autorisés.",

	// sign in, sign up and sessions
	'OZ_AUTH_USER_UNKNOWN'                      => "Aucun utilisateur n'est associé à ces informations de connexion.",
	'OZ_AUTH_INVALID_CREDENTIALS'               => 'Identifiants incorrects.',
	'OZ_AUTH_USER_UNVERIFIED'                   => "Ce compte n'est pas encore vérifié.",
	'OZ_AUTH_TOO_MUCH_ATTEMPT'                  => 'Trop de tentatives. Veuillez réessayer plus tard.',
	'OZ_2FA_REQUIRED'                           => "Un second facteur d'authentification est requis.",
	'OZ_2FA_NO_PENDING_USER'                    => "Aucune connexion n'attend de second facteur.",
	'OZ_2FA_SESSION_MISMATCH'                   => 'Cette vérification appartient à une autre session.',
	'OZ_USER_SIGN_UP_SUCCESS'                   => 'Inscription réussie.',
	'OZ_USER_SIGN_IN_DONE'                      => 'Vous êtes connecté.',
	'OZ_USER_LOGOUT_DONE'                       => 'Vous vous êtes déconnecté.',
	'OZ_USER_LOG_ON_FAIL'                       => 'La connexion a échoué.',
	'OZ_USER_LOG_OUT_FAIL'                      => 'La déconnexion a échoué.',
	'OZ_ACCOUNT_RECOVERY_SUCCESS'               => 'Votre compte a été récupéré.',
	'OZ_PASSWORD_EDIT_SUCCESS'                  => 'Votre mot de passe a été modifié.',
	'OZ_PASSWORD_SAME_OLD_AND_NEW_PASS'         => "Le nouveau mot de passe doit être différent de l'actuel.",
	'OZ_SESSION_HIJACKING_DETECTED'             => 'Votre session a été interrompue pour des raisons de sécurité.',
	'OZ_SESSION_DELETION_FAILED'                => "La session n'a pas pu être supprimée.",
	'OZ_SESSION_DISTINCT_USER_CANT_ATTACH_USER' => 'Un autre utilisateur est déjà connecté sur cette session.',

	// fields
	'OZ_FIELD_PHONE_ALREADY_REGISTERED'      => 'Le numéro {phone} est déjà associé à un autre compte.',
	'OZ_FIELD_EMAIL_ALREADY_REGISTERED'      => "L'adresse mail {email} est déjà associée à un autre compte.",
	'OZ_FIELD_EMAIL_INVALID'                 => "L'adresse mail n'est pas valide.",
	'OZ_FIELD_PHONE_INVALID'                 => 'Le numéro est invalide.',
	'OZ_FIELD_PHONE_NOT_REGISTERED'          => "Ce numéro n'est pas inscrit.",
	'OZ_FIELD_EMAIL_NOT_REGISTERED'          => "Cette adresse mail n'est pas enregistrée.",
	'OZ_FIELD_COUNTRY_INVALID'               => 'Le pays est invalide.',
	'OZ_FIELD_COUNTRY_UNKNOWN'               => 'Pays inconnu.',
	'OZ_FIELD_COUNTRY_NOT_ALLOWED'           => "Le pays spécifié n'est pas valide. Le service n'est peut-être pas encore dans votre pays.",
	'OZ_FIELD_USER_NAME_INVALID'             => "Le nom d'utilisateur est invalide.",
	'OZ_FIELD_USER_NAME_INVALID_CHARACTERS'  => "Le nom d'utilisateur contient des caractères non autorisés.",
	'OZ_FIELD_USER_NAME_TOO_SHORT'           => "Le nom d'utilisateur est trop court.",
	'OZ_FIELD_USER_NAME_TOO_LONG'            => "Le nom d'utilisateur est trop long.",
	'OZ_FIELD_USER_NAME_ALREADY_REGISTERED'  => "Le nom d'utilisateur est déjà pris.",
	'OZ_FIELD_USER_NAME_NOT_REGISTERED'      => "Aucun compte n'est associé à ce nom d'utilisateur.",
	'OZ_FIELD_URL_INVALID'                   => "L'adresse est invalide.",
	'OZ_FIELD_URL_HOST_NOT_ALLOWED'          => "Cette adresse n'est pas autorisée.",
	'OZ_FIELD_GENDER_INVALID'                => 'Le genre est invalide.',
	'OZ_FIELD_PASS_INVALID'                  => 'Le mot de passe est incorrect.',
	'OZ_FIELD_PASS_NOT_SECURE'               => "Le mot de passe n'est pas assez sécurisé.",
	'OZ_FIELD_PASS_TOO_LONG'                 => 'Le mot de passe est trop long.',
	'OZ_FIELD_PASS_TOO_SHORT'                => 'Le mot de passe est trop court.',
	'OZ_FIELD_SHOULD_HAVE_SAME_VALUE'        => 'Les champs {field} et {field_confirm} doivent avoir la même valeur.',

	// files
	'OZ_FILE_INVALID'                        => 'Le fichier est invalide.',
	'OZ_FILE_MIME_INVALID'                   => "Ce type de fichier n'est pas autorisé.",
	'OZ_FILE_SIZE_OUT_OF_RANGE'              => 'La taille du fichier est hors de la plage autorisée.',
	'OZ_FILE_COUNT_OUT_OF_RANGE'             => 'Le nombre de fichiers est hors de la plage autorisée.',
	'OZ_FILE_TOTAL_SIZE_EXCEED_LIMIT'        => 'Les fichiers dépassent la taille totale autorisée.',
	'OZ_FILE_UPLOAD_FAILS'                   => "Échec de l'envoi du ou des fichiers.",
	'OZ_FILE_UPLOAD_IS_EMPTY'                => "Aucun fichier n'a été envoyé.",
	'OZ_FILE_UPLOAD_TOO_BIG'                 => 'Le fichier est trop volumineux.',
	'OZ_FILE_INFECTED'                       => 'Le fichier contient un virus et a été refusé.',
	'OZ_FILE_SCAN_FAILED'                    => "Le fichier n'a pas pu être analysé, veuillez réessayer.",
	'OZ_CAPTCHA_CODE_EXPIRED'                => 'Le captcha a expiré.',
	'OZ_QR_CODE_HAS_EXPIRED'                 => 'Le code QR a expiré.',
	'OZ_LINK_EXPIRED'                        => 'Ce lien a expiré.',

	// forms and form sessions
	'OZ_FORM_MISSING_REQUIRED_FIELD'         => 'Un champ obligatoire est manquant.',
	'OZ_FORM_PROVIDER_NOT_FOUND'             => 'Formulaire « {provider} » introuvable.',
	'OZ_FORM_PROVIDER_REQUIRES_REAL_CONTEXT' => 'Ce formulaire doit être rempli depuis sa route.',
	'OZ_FORM_RESUME_EXPIRED'                 => 'Cette session de formulaire a expiré.',
	'OZ_FORM_RESUME_NOT_YET_ACTIVE'          => "Ce formulaire n'est pas encore ouvert.",
	'OZ_FORM_RESUME_INVALID_ACTION'          => 'Action de formulaire « {action} » inconnue.',
	'OZ_FORM_SESSION_NOT_FOUND'              => 'Session de formulaire introuvable ou expirée.',
	'OZ_FORM_SESSION_REF_MISSING'            => 'La référence de la session de formulaire est manquante.',
	'OZ_FORM_SESSION_ACCESS_DENIED'          => 'Vous ne pouvez pas accéder à cette session de formulaire.',
	'OZ_FORM_SESSION_PROVIDER_MISMATCH'      => 'Cette session appartient à un autre formulaire.',
	'OZ_FORM_SESSION_ROUTE_MISMATCH'         => 'Cette session de formulaire appartient à une autre route.',
	'OZ_FORM_SESSION_ALREADY_DONE'           => 'Cette session de formulaire est déjà terminée.',
	'OZ_FORM_SESSION_NOT_DONE'               => "Cette session de formulaire n'est pas encore terminée.",
	'OZ_FORM_SESSION_NOT_YET_ACTIVE'         => 'Cette session de formulaire ne peut pas encore commencer.',
	'OZ_FORM_SESSION_NOT_REVERSIBLE'         => 'Ce formulaire ne permet pas de revenir en arrière.',
	'OZ_FORM_SESSION_NO_HISTORY'             => 'Vous êtes déjà à la première étape.',

	// REST relations
	'OZ_RELATION_NOT_DEFINED'                                                  => 'Relation demandée inconnue.',
	'OZ_RELATION_ARRAY_EXPECTED'                                               => 'Les données de la relation doivent être une liste.',
	'OZ_RELATION_IS_PAGINATED_ARRAY_OF_ARRAY_EXPECTED'                         => "Cette relation paginée attend une liste d'éléments.",
	'OZ_RELATION_IS_PAGINATED_AND_SHOULD_BE_RETRIEVED_WITH_DEDICATED_ENDPOINT' => "Cette relation est paginée : récupérez-la via son propre point d'accès.",
	'OZ_RELATION_PROCESSING_FAILED'                                            => "La relation n'a pas pu être traitée.",

	// used in views templates
	'OZ_VIEW_GO_HOME_BTN'                    => 'Accueil',
	'OZ_VIEW_GO_BACK_BTN'                    => 'Retour',
	'OZ_VIEW_REDIRECT_TITLE'                 => 'Vous allez être redirigé.',
	'OZ_VIEW_REDIRECT_MESSAGE'               => "Si vous n'êtes pas redirigé automatiquement, suivez ce <a href=\"{url}\">lien</a>.",
	'OZ_VIEW_AUTH_LINK_CONFIRM_TITLE'        => 'Confirmation',
	'OZ_VIEW_AUTH_LINK_CONFIRM_MESSAGE'      => 'Cliquez sur le bouton ci-dessous pour confirmer.',
	'OZ_VIEW_AUTH_LINK_CONFIRM_BTN'          => 'Confirmer',

	// access grant form view
	'OZ_ACCESS_GRANT_FORM_TITLE'             => 'Autorisation requise',
	'OZ_ACCESS_GRANT_FORM_SUBMIT_BTN'        => 'Autoriser',

	// welcome page view
	'OZ_WELCOME_PAGE_TITLE'                  => 'Bienvenue',
	'OZ_WELCOME_PAGE_API_DOC_LINK'           => 'Documentation API',
];
