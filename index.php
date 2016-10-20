<?php

class_exists('CApi') or die();

CApi::Inc('common.plugins.change-password');

class ChMailServerChangePasswordPlugin extends AApiChangePasswordPlugin
{
	/**
	 * @var
	 */
	protected $oBaseApp;

	/**
	 * @var
	 */
	protected $oAdminAccount;

	/**
	 * @param CApiPluginManager $oPluginManager
	 */
	public function __construct(CApiPluginManager $oPluginManager)
	{
		parent::__construct('1.0', $oPluginManager);

		$this->oBaseApp = null;
		$this->oAdminAccount = null;
	}
	
	protected function initializeServer()
	{
		if (null === $this->oBaseApp)
		{
			if (class_exists('COM'))
			{
				$this->oBaseApp = new COM("hMailServer.Application");
				try
				{
					$this->oBaseApp->Connect();

					// Authenticate the user
					$this->oAdminAccount = $this->oBaseApp->Authenticate(
						CApi::GetConf('plugins.hmailserver-change-password.config.login', 'Administrator'),
						CApi::GetConf('plugins.hmailserver-change-password.config.password', '')
					);
				}
				catch(Exception $oException)
				{
					\CApi::Log('Initialize Server Error');
					\CApi::LogObject($oException);
				}
			}
			else 
			{
				\CApi::Log('Unable to load class: COM');
			}
		}		
	}

	/**
	 * @param CAccount $oAccount
	 * @return bool
	 */
	protected function isLocalAccount($oAccount)
	{
		return \in_array(\strtolower(\trim($oAccount->IncomingMailServer)), array(
		   'localhost', '127.0.0.1', '::1', '::1/128', '0:0:0:0:0:0:0:1'
		  ));		
	}
	
	/**
	 * @param CAccount $oAccount
	 * @return object
	 */
	protected function getServerDomain($oAccount)
	{
		$oDomain = null;
		$this->initializeServer();

		if (($oAccount instanceof CAccount) && $this->oBaseApp && $this->oAdminAccount)
		{
			$sDomainName = $oAccount->Domain->Name;
			if ($this->isLocalAccount($oAccount))
			{
				list($sLogin, $sDomainName) = explode('@', $oAccount->Email);
			}
			
			try
			{
				$oDomain = $this->oBaseApp->Domains->ItemByName($sDomainName);
			}
			catch(Exception $oException) 
			{
				\CApi::Log('Getting domain error');
				\CApi::LogObject($oException);
			}
		}
		
		return $oDomain;
	}

	/**
	 * @param CAccount $oAccount
	 * @return bool
	 */
	protected function validateIfAccountCanChangePassword($oAccount)
	{
		return ($this->getServerDomain($oAccount) !== null);
	}

	/**
	 * @param CAccount $oAccount
	 */
	public function ChangePasswordProcess($oAccount)
	{
		if (0 < strlen($oAccount->PreviousMailPassword) &&
			$oAccount->PreviousMailPassword !== $oAccount->IncomingMailPassword)
		{
			$this->initializeServer();

			if ($this->oBaseApp && $this->oAdminAccount)
			{
				try
				{
					$oDomain = $this->getServerDomain($oAccount);
					if ($oDomain !== null)
					{
						$sEmail = $oAccount->Email;
						$oServerAccount = $oDomain->Accounts->ItemByAddress($sEmail);
						if ($oServerAccount !== null)
						{
							$oServerAccount->Password = $oAccount->IncomingMailPassword;
							$oServerAccount->Save();
						}
					}
				}
				catch (Exception $oException)
				{
					throw new CApiManagerException(Errs::UserManager_AccountNewPasswordUpdateError);
				}
			}
			else
			{
				throw new CApiManagerException(Errs::UserManager_AccountNewPasswordUpdateError);
			}
		}
	}
}

return new ChMailServerChangePasswordPlugin($this);
