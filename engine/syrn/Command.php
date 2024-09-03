<?php
namespace Ntuple\Synctree\Syrn;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends BaseCommand
{
    /** @var array<string,string> */
    protected $arguments;

    /** @var array<string,string> */
    protected $options;

    /** @var InputInterface */
    protected $input;

    /** @var OutputInterface */
    protected $output;
}
