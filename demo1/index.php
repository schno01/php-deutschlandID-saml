<?php

/**
 *  SAML Handler
 */

session_start();

require_once dirname(__DIR__).'/_toolkit_loader.php';

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Utils;

require_once 'settings.php';

$auth = new Auth($settingsInfo);

if (isset($_GET['sso'])) {
    $auth->login();

    # If AuthNRequest ID need to be saved in order to later validate it, do instead
    # $ssoBuiltUrl = $auth->login(null, array(), false, false, true);
    # $_SESSION['AuthNRequestID'] = $auth->getLastRequestID();
    # header('Pragma: no-cache');
    # header('Cache-Control: no-cache, must-revalidate');
    # header('Location: ' . $ssoBuiltUrl);
    # exit();

} else if (isset($_GET['sso2'])) {
    $returnTo = $spBaseUrl.'/demo1/attrs.php';
    $auth->login($returnTo);
} else if (isset($_GET['slo'])) {
    $returnTo = null;
    $paramters = array();
    $nameId = null;
    $sessionIndex = null;
    $nameIdFormat = null;
    $nameIdNameQualifier = null;
    $nameIdSPNameQualifier = null;

    if (isset($_SESSION['samlNameId'])) {
        $nameId = $_SESSION['samlNameId'];
    }
    if (isset($_SESSION['samlNameIdFormat'])) {
        $nameIdFormat = $_SESSION['samlNameIdFormat'];
    }
    if (isset($_SESSION['samlNameIdNameQualifier'])) {
        $nameIdNameQualifier = $_SESSION['samlNameIdNameQualifier'];
    }
    if (isset($_SESSION['samlNameIdSPNameQualifier'])) {
        $nameIdSPNameQualifier = $_SESSION['samlNameIdSPNameQualifier'];
    }
    if (isset($_SESSION['samlSessionIndex'])) {
        $sessionIndex = $_SESSION['samlSessionIndex'];
    }

    $auth->logout($returnTo, $paramters, $nameId, $sessionIndex, false, $nameIdFormat, $nameIdNameQualifier, $nameIdSPNameQualifier);

    # If LogoutRequest ID need to be saved in order to later validate it, do instead
    # $sloBuiltUrl = $auth->logout(null, $paramters, $nameId, $sessionIndex, true);
    # $_SESSION['LogoutRequestID'] = $auth->getLastRequestID();
    # header('Pragma: no-cache');
    # header('Cache-Control: no-cache, must-revalidate');
    # header('Location: ' . $sloBuiltUrl);
    # exit();

} else if (isset($_GET['acs'])) {
    if (isset($_SESSION) && isset($_SESSION['AuthNRequestID'])) {
        $requestID = $_SESSION['AuthNRequestID'];
    } else {
        $requestID = null;
    }

    $auth->processResponse($requestID);

    $errors = $auth->getErrors();

    if (!empty($errors)) {
        echo '<p>' . htmlentities(implode(', ', $errors)) . '</p>';
        if ($auth->getSettings()->isDebugActive()) {
            echo '<p>'.htmlentities($auth->getLastErrorReason()).'</p>';
        }
    }

    if (!$auth->isAuthenticated()) {
        echo '<p>Not authenticated</p>';
        exit();
    }

    $_SESSION['samlUserdata'] = $auth->getAttributes();
    $_SESSION['samlNameId'] = $auth->getNameId();
    $_SESSION['samlNameIdFormat'] = $auth->getNameIdFormat();
    $_SESSION['samlNameIdNameQualifier'] = $auth->getNameIdNameQualifier();
    $_SESSION['samlNameIdSPNameQualifier'] = $auth->getNameIdSPNameQualifier();
    $_SESSION['samlSessionIndex'] = $auth->getSessionIndex();

    unset($_SESSION['AuthNRequestID']);
    if (isset($_POST['RelayState']) && Utils::getSelfURL() != $_POST['RelayState']) {
        // To avoid 'Open Redirect' attacks, before execute the
        // redirection confirm the value of $_POST['RelayState']
        // is a trusted URL.
        $auth->redirectTo($_POST['RelayState']);
    }
} else if (isset($_GET['sls'])) {
    if (isset($_SESSION) && isset($_SESSION['LogoutRequestID'])) {
        $requestID = $_SESSION['LogoutRequestID'];
    } else {
        $requestID = null;
    }

    $auth->processSLO(false, $requestID);
    $errors = $auth->getErrors();
    if (empty($errors)) {
        echo '<p>Successfully logged out</p>';
    } else {
        echo '<p>' . htmlentities(implode(', ', $errors)) . '</p>';
        if ($auth->getSettings()->isDebugActive()) {
            echo '<p>'.htmlentities($auth->getLastErrorReason()).'</p>';
        }
    }
}

if (isset($_SESSION['samlUserdata'])) {
    if (!empty($_SESSION['samlUserdata'])) {
        $attributes = $_SESSION['samlUserdata'];
        echo 'You have the following attributes:<br>';
        echo '<table><thead><th>Name</th><th>Values</th></thead><tbody>';
        foreach ($attributes as $attributeName => $attributeValues) {
            echo '<tr><td>' . htmlentities($attributeName) . '</td><td><ul>';
            foreach ($attributeValues as $attributeValue) {
                echo '<li>' . htmlentities($attributeValue) . '</li>';
            }
            echo '</ul></td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo "<p>You don't have any attribute</p>";
    }

    echo '<p><a href="?slo" >Logout</a></p>';
} else {
    echo '<p><a href="?sso" >Login</a></p>';
    echo '<p><a href="?sso2" >Login and access to attrs.php page</a></p>';
}
