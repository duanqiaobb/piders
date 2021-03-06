<?php
namespace Pider\Console\Command;

/**
 * Define pider command under console
 */
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Pider\Support\SpiderWise;
use Pider\Console\Command;
use Pider\Config;
use Pider\Log\Log as Logger;
use Pider\Support\UrlXlsxExtractor;

class Pider extends Command {

    public $isExe= true;

    protected function configure() {
        $this->setName('pider')
             ->setDescription("Project tools for <info>pider</info>")
             ->setHidden(true);
    }

    public function subCommands() {
        $subcommands = [];
        //define `list` subcommand
        $subcommands[] = (new class() extends Command {
            public function configure() {
                $this->setName('list')
                     ->setDescription("list all availabe spiders");
            }
        })->setCode(function($in,$out){
            $spider_pathes = Config::get('Spiders');
            $spiderwise = new SpiderWise();
            $spiders = [];
            foreach ($spider_pathes as $path) {
                $spider_container = $spiderwise->listSpider($path);
                $spiders = array_merge($spiders,$spider_container);
            }
            $io = new SymfonyStyle($in,$out);
            $out->writeln(['<comment>All Available Spiders:</comment>']);
            $io->listing($spiders);

        });

        // define `crawl` subcommand
        $subcommands[] = (new class() extends Command {
            public function configure() {
                $this->setName('crawl')
                     ->setDescription("crawl urls supplied")
                     ->addArgument('url',InputArgument::OPTIONAL,'url to crawled')
                     ->addOption('file','f',InputOption::VALUE_REQUIRED,'file contains urls to be crawled')
                     ->addOption('spider','s',InputOption::VALUE_OPTIONAL,'spider be appointed')
                     ->addOption('filetype','t',InputOption::VALUE_OPTIONAL,'filetype specified, defaults: txt ')
                     ->addOption('attach','a',InputOption::VALUE_OPTIONAL,'data will be attached to request,json format')
                     ->addOption('loglevel','l',InputOption::VALUE_OPTIONAL,'log which matches level option will output ');
            }

            public function execute(InputInterface $in,OutputInterface $out) {
                $logger = Logger::getLogger();
                $io = new SymfonyStyle($in,$out);
                $loglevel = $in->getOption('loglevel');
                if (!empty($loglevel)) {
                        defined('LOG_LEVEL')?'':define('LOG_LEVEL',$loglevel);
                } else {
                        defined('LOG_LEVEL')?'':define('LOG_LEVEL','OUTPUT');
                }
                $url = $in->getArgument('url');
                $file = $in->getOption('file');
                if(!empty($url) && empty($file)) {
                    $attach = $in->getOption('attach');
                    $spider = $in->getOption('spider');
                   if(!empty($url)) {
                        $attach = !empty($attach) && !is_null(@json_decode($attach)) ?json_decode($attach,true):[];
                        if(!empty($spider)) {
                            SpiderWise::dispatchSpider([$url],1,$attach,$spider);
                        } else  {
                            SpiderWise::dispatchSpider([$url],1,$attach);
                        }
                    }
                }
                if (empty($url) && !empty($file)) {
                    $real_file = realpath($file);
                    $spider = $in->getOption('spider');
                    if (!file_exists($real_file)) {
                        $logger->error('File '.$real_file.' doesn\'t exists');
                    }
                    $detect_type = pathinfo($real_file,PATHINFO_EXTENSION);
                    $filetype = $in->getOption('filetype');
                    $filetype = empty($filetype)?$detect_type:$filetype;
                    if(!empty($filetype)) {
                        $filetype = strtolower($filetype);
                        if ($filetype == 'xlsx') {
                            $urls = (new UrlXlsxExtractor($real_file))->extract();
                            if (!empty($urls)) {
                                if (!empty($spider)) {
                                    SpiderWise::dispatchSpider($urls,100,[],$spider);
                                } else {
                                    SpiderWise::dispatchSpider($urls,100);
                                }
                            }
                       }
                    }
                }
                parent::execute($in,$out);
            }
        });
        //define `runspider` subcommand
        $subcommands[] = (new class() extends Command {
            public function configure() {
                $this->setName('runspider')
                    ->setDescription("run a spider")
                    ->addArgument('spidername',InputArgument::OPTIONAL,'spider name to be run; you can just specify a name or specify a valid path.');
            }

            public function execute(InputInterface $in, OutputInterface $out) {
                $spider = $in->getArgument('spidername');
                $io = new SymfonyStyle($in,$out);
                if (!empty($spider)) {
                    $status = SpiderWise::runSpider($spider);
                    if (!$status) {
                        $io->newLine();
                        $io->writeln(['<options=bold>'.$spider.' does\'t exist or is not a valid spider'.'.</>']);
                        $io->newLine();
                        $io->writeln(['<comment>Available spiders:</comment>']);
                        $spiders = SpiderWise::allAvailableSpiders(false);
                        $io->listing($spiders);
                    }
                }
                parent::execute($in,$out);
            } 

        });

        //define 'rundigest' subcommand
        $subcommands[] = (new class() extends Command {
            public function configure() {
                $this->setName('rundigest')
                    ->setDescription("run a digest")
                    ->addArgument('digestname',InputArgument::OPTIONAL,'digest name to be run; you can just specify a name or specify a valid path.');
            }

            public function execute(InputInterface $in, OutputInterface $out) {
                parent::execute($in,$out);
            } 

        });




        // define `checkurl` subcommand
        $subcommands[] = (new class() extends Command {
            public function configure() {
                $this->setName('checkurl')
                    ->setDescription("check url can be crawled by which spiders")
                    ->addArgument('url',InputArgument::OPTIONAL,'url to be checked');
            }
            public function execute(InputInterface $in, OutputInterface $out) {
                $spiderwise = new SpiderWise();
                $io = new SymfonyStyle($in,$out);
                if($in->hasArgument('url')) {
                    $url = $in->getArgument('url');
                    $io->writeln(['<comment>URL:</comment>']);
                    $io->newLine();
                    $io->text($url);
                    $io->newLine();
                    $io->writeln(['<comment>Available spiders:</comment>']);
                    $available_spiders = $spiderwise->linkSpider($url);
                    if (!empty($available_spiders)) {
                        $io->newLine();
                        $io->listing($available_spiders);
                    } else {
                        $io->newLine();
                        $io->text('None');
                    }
                }
                parent::execute($in,$out);
            } 
        });
        return $subcommands;
    }

    public function execute(InputInterface $input, OutputInterface $output) {
        $io = new SymfonyStyle($input,$output);
        $ascii="
.______    __   _______   _______ .______      
|   _  \  |  | |       \ |   ____||   _  \     
|  |_)  | |  | |  .--.  ||  |__   |  |_)  |    
|   ___/  |  | |  |  |  ||   __|  |      /     
|  |      |  | |  '--'  ||  |____ |  |\  \----.
| _|      |__| |_______/ |_______|| _| `._____|";
        $io->writeln($ascii);
        $command_list = $this->list();
        $io->writeln(array("<comment>Usage:</comment>"));
        $io->text('<info>./'.$this->getName().' [command]</info>');
        $io->newLine();
        $io->writeln(array("<comment>Description:</comment>"));
        $io->text($this->getDescription());
        $io->newLine();
        $io->writeln(array("<comment>Available commands:</comment>"));
        $io->text($command_list);
    }
}

