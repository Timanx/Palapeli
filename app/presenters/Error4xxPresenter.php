<?php

namespace App\Presenters;

use Nette;


class Error4xxPresenter extends BasePresenter
{

	public function startup()
	{
		parent::startup();
		if (!$this->getRequest()->isMethod(Nette\Application\Request::FORWARD)) {
			$this->error();
		}
	}


	public function renderDefault(Nette\Application\BadRequestException $exception)
	{
        parent::render();
        $code = $exception->getCode();
        switch($code) {
            case 404:
                $this->prepareHeading('Stránka nenalezena');
                break;
            case 403:
                $this->prepareHeading('Přístup zamítnut');
                break;
            case 405:
                $this->prepareHeading('Nepodporovaná metoda');
                break;
            case 410:
                $this->prepareHeading('Nedostupný obsah');
                break;
            default:
                $this->prepareHeading('Chyba');
                break;
        }

		// load template 403.latte or 404.latte or ... 4xx.latte
		$file = __DIR__ . "/templates/Error/{$exception->getCode()}.latte";
		$this->template->setFile(is_file($file) ? $file : __DIR__ . '/templates/Error/4xx.latte');
	}

}
