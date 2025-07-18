<?php
/**
 * This file is part of php-saml.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package OneLogin
 * @author  Sixto Martin <sixto.martin.garcia@gmail.com>
 * @license MIT https://github.com/SAML-Toolkits/php-saml/blob/master/LICENSE
 * @link    https://github.com/SAML-Toolkits/php-saml
 */

namespace OneLogin\Saml2;

/**
 * SAML 2 Authentication Request
 */
class AuthnRequest
{
    /**
     * Object that represents the setting info
     *
     * @var Settings
     */
    protected $_settings;

    /**
     * SAML AuthNRequest string
     *
     * @var string
     */
    private $_authnRequest;

    /**
     * SAML AuthNRequest ID.
     *
     * @var string
     */
    private $_id;

    /**
     * Constructs the AuthnRequest object.
     *
     * @param Settings $settings SAML Toolkit Settings
     * @param bool $forceAuthn When true the AuthNReuqest will set the ForceAuthn='true'
     * @param bool $isPassive When true the AuthNReuqest will set the Ispassive='true'
     * @param bool $setNameIdPolicy When true the AuthNReuqest will set a nameIdPolicy
     * @param string $nameIdValueReq Indicates to the IdP the subject that should be authenticated
     */
    public function __construct(\OneLogin\Saml2\Settings $settings, $forceAuthn = false, $isPassive = false, $setNameIdPolicy = true, $nameIdValueReq = null)
    {
        $this->_settings = $settings;

        $spData = $this->_settings->getSPData();
        $security = $this->_settings->getSecurityData();

        $id = Utils::generateUniqueID();
        $issueInstant = Utils::parseTime2SAML(time());

        $subjectStr = "";
        if (isset($nameIdValueReq)) {
            $subjectStr = <<<SUBJECT

     <saml:Subject>
        <saml:NameID Format="{$spData['NameIDFormat']}">{$nameIdValueReq}</saml:NameID>
        <saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer"></saml:SubjectConfirmation>
    </saml:Subject>
SUBJECT;
        }

        $nameIdPolicyStr = '';
        if ($setNameIdPolicy) {
            $nameIDPolicyFormat = $spData['NameIDFormat'];
            if (isset($security['wantNameIdEncrypted']) && $security['wantNameIdEncrypted']) {
                $nameIDPolicyFormat = Constants::NAMEID_ENCRYPTED;
            }

            $nameIdPolicyStr = <<<NAMEIDPOLICY

    <samlp:NameIDPolicy
        Format="{$nameIDPolicyFormat}"
        AllowCreate="true" />
NAMEIDPOLICY;
        }


        $providerNameStr = '';
        $organizationData = $settings->getOrganization();
        if (!empty($organizationData)) {
            $langs = array_keys($organizationData);
            if (in_array('en-US', $langs)) {
                $lang = 'en-US';
            } else {
                $lang = $langs[0];
            }
            if (isset($organizationData[$lang]['displayname']) && !empty($organizationData[$lang]['displayname'])) {
                $providerNameStr = <<<PROVIDERNAME
    ProviderName="{$organizationData[$lang]['displayname']}"
PROVIDERNAME;
            }
        }

        $forceAuthnStr = '';
        if ($forceAuthn) {
            $forceAuthnStr = <<<FORCEAUTHN

    ForceAuthn="true"
FORCEAUTHN;
        }

        $isPassiveStr = '';
        if ($isPassive) {
            $isPassiveStr = <<<ISPASSIVE

    IsPassive="true"
ISPASSIVE;
        }

        $requestedAuthnStr = '';
        if (isset($security['requestedAuthnContext']) && $security['requestedAuthnContext'] !== false) {
            $authnComparison = 'exact';
            if (isset($security['requestedAuthnContextComparison'])) {
                $authnComparison = $security['requestedAuthnContextComparison'];
            }

            $authnComparisonAttr = '';
            if (!empty($authnComparison)) {
                $authnComparisonAttr = sprintf('Comparison="%s"', $authnComparison);
            }

            if ($security['requestedAuthnContext'] === true) {
                $requestedAuthnStr = <<<REQUESTEDAUTHN

    <samlp:RequestedAuthnContext $authnComparisonAttr>
        <saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef>
    </samlp:RequestedAuthnContext>
REQUESTEDAUTHN;
            } else {
                $requestedAuthnStr .= "    <samlp:RequestedAuthnContext $authnComparisonAttr>\n";
                foreach ($security['requestedAuthnContext'] as $contextValue) {
                    $requestedAuthnStr .= "        <saml:AuthnContextClassRef>".$contextValue."</saml:AuthnContextClassRef>\n";
                }
                $requestedAuthnStr .= '    </samlp:RequestedAuthnContext>';
            }
        }

        $spEntityId = htmlspecialchars($spData['entityId'], ENT_QUOTES);
        $acsUrl = htmlspecialchars($spData['assertionConsumerService']['url'], ENT_QUOTES);
        $destination = $this->_settings->getIdPSSOUrl();
        $request = <<<AUTHNREQUEST
<samlp:AuthnRequest
    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID="$id"
    Version="2.0"
{$providerNameStr}{$forceAuthnStr}{$isPassiveStr}
    IssueInstant="{$issueInstant}"
    Destination="{$destination}"
    ProtocolBinding="{$spData['assertionConsumerService']['binding']}"
    AssertionConsumerServiceURL="{$acsUrl}">
    <saml:Issuer>{$spEntityId}</saml:Issuer>{$subjectStr}{$nameIdPolicyStr}{$requestedAuthnStr}
</samlp:AuthnRequest>
AUTHNREQUEST;

        $this->_id = $id;
        $request = $this->change4deid($request);
        $this->_authnRequest = $request;
    }

    /**
     * Returns deflated, base64 encoded, unsigned AuthnRequest.
     *
     * @param bool|null $deflate Whether or not we should 'gzdeflate' the request body before we return it.
     *
     * @return string
     */
    public function getRequest($deflate = null)
    {
        $subject = $this->_authnRequest;

        if (is_null($deflate)) {
            $deflate = $this->_settings->shouldCompressRequests();
        }

        if ($deflate) {
            $subject = gzdeflate($this->_authnRequest);
        }

        $base64Request = base64_encode($subject);
        return $base64Request;
    }

    public function setRequest($subject = null)
    {
        $this->_authnRequest = $subject;

        return;
    }


    /**
     * Returns the AuthNRequest ID.
     *
     * @return string
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * Returns the XML that will be sent as part of the request
     *
     * @return string
     */
    public function getXML()
    {
        return $this->_authnRequest;
    }

    /**
     * Add AKDB EXTENSION AND MORE INFO
     * @param string $request
     * @return string
     */
    private function change4deid(string $request):string
    {


        $security = $this->_settings->getSecurityData();

        $request = str_replace('<saml:Issuer>','<saml2:Issuer xmlns:saml2="urn:oasis:names:tc:SAML:2.0:assertion">',$request);
        $request = str_replace('</saml:Issuer>','</saml2:Issuer>',$request);
        $request = str_replace('<samlp:AuthnRequest','<saml2p:AuthnRequest xmlns:saml2p="urn:oasis:names:tc:SAML:2.0:protocol"',$request);


if(!isset($this->_settings->getSPData()['attributeConsumingService']['requestedAttributes'])){
    throw new Error(
        'SAML requestedAttributes missing',
        Error::METADATA_SP_INVALID
    );
}
        if(!isset($this->_settings->getOrganization()['en']['displayname'])){
            throw new Error(
                'SAML Organization displayname [en] missing',
                Error::METADATA_SP_INVALID
            );
        }



        $requestedAttributes = $this->_settings->getSPData()['attributeConsumingService']['requestedAttributes'];

        $add='<saml2p:Extensions>
<akdb:AuthenticationRequest xmlns:akdb="https://www.akdb.de/request/2018/09" EnableStatusDetail="true"
Version="2"
>
<akdb:RequestedAttributes>';
        foreach ($requestedAttributes as $requestedAttribute) {
            $add.='<akdb:RequestedAttribute Name="'.$requestedAttribute['name'].'"
RequiredAttribute="'.($requestedAttribute['isRequired']?'true':'false').'"
/>';
        }
        $add.='</akdb:RequestedAttributes>
<akdb:DisplayInformation>
<classic-ui:Version xmlns:classic-ui="https://www.akdb.de/request/2018/09/classic-ui/v1">
<classic-ui:OrganizationDisplayName>'.$this->_settings->getOrganization()['en']['displayname'].'</classic-ui:OrganizationDisplayName>
</classic-ui:Version>
</akdb:DisplayInformation>
</akdb:AuthenticationRequest>
</saml2p:Extensions>';

        $add.='<saml2p:RequestedAuthnContext Comparison="minimum">
<saml2:AuthnContextClassRef xmlns:saml2="urn:oasis:names:tc:SAML:2.0:assertion">STORK-QAA-Level-1</saml2:AuthnContextClassRef>
</saml2p:RequestedAuthnContext>';

        $request = str_replace('</samlp:AuthnRequest',$add.'</saml2p:AuthnRequest',$request);

        return $request;
    }
}
