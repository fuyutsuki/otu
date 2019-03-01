<?php

declare(strict_types = 1);

namespace otu;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\Player;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\level\Position;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;

class otu extends PluginBase implements Listener {

	/** @var Config */
	private $config;
	/** @var Config */
	private $otu;
	/** @var Config */
	private $setting;
	/** @var ?Level */
	private $level;

	/**
	 * サーバー開始時の処理//プラグインが有効になると実行されるメソッド
	 */
	public function onEnable() {
		$this->initResources();
		jail::init();//Jailを呼び出しておく
		$this->loadLevelWithJail();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);//イベント登録
	}

	private function initResources() {
		$dir = $this->getDataFolder();
		$files = [
			"setting.yml",
			"config.yml",
			"otu.yml"
		];
		foreach($files as $file) {
			$this->saveResource($file);
		}
		$this->config = new Config("{$dir}config.yml", Config::YAML);
		$this->otu = new Config("{$dir}otu.yml", Config::YAML);
		$this->setting = new Config("{$dir}setting.yml", Config::YAML);
	}

	private function loadLevelWithJail() {
		$xyz = explode(',', $this->config->get("xyz"));//x,y,z,worldを配列に変換
		$target = $xyz[3];
		$this->getServer()->loadLevel($target);
		$this->level = $this->getServer()->getLevelByName($target);
		if ($this->level !== null) {
			$this->getLogger()->notice("ワールド {$target} を使用します");
		}else {
			$this->getLogger()->error("config.yml で指定されたワールド {$target} は存在しません");
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}
	}
	
	//コマンド処理
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
		switch (strtolower($label)) {
			case "otu":// otuコマンド実行時の処理
				if(empty($args[0])) return false;// 例外回避
				$target = $args[0];
				$player = $this->getServer()->getPlayer($target);// プレーヤー名取得
				if ($player !== null) {// プレーヤーが存在するかをチェック
					if (!$this->otu->exists($target)) {// otuされてるかを確認!
						$this->otu->set($target, "true");// otuリストに追加!
						$this->otu->save();// セーブ
						$xyz = explode(',', $this->config->get("xyz"));//x,y,zを配列に変換
						$v = new Position($xyz[0], $xyz[1], $xyz[2], $this->level);//座標指定
						$player->teleport($v);//ターゲットを指定した座標へtp!
						$sender->sendMessage("[乙] ( ﾟω^ )ゝ " . $player->getName() . "さんを牢屋へTP !しました");//コマンド実行者にメッセージ
						$player->sendMessage("[乙] " . $this->setting->get("otu.cmdm.otu.arasi.now"));//ターゲットへのめっせーじ
					}else{//セットされていれば以下の処理
						$this->otu->remove($player->getName());//otuリストから削除
						$this->otu->save();//セーブ
						if($this->setting->get("syakuhou") == "true"){
							$cmd = $this->setting->get("otuoffcmd");
							if(isset($cmd)){
								$this->CPR($cmd, $sender, $player);//関数側で処理
							}else{
								$this->getLogger()->warning("[乙] setting.ymlファイルのotucmdoffを正しく設定してください");
							}
						}
						$sender->sendMessage("[乙] ( ﾟω^ )ゝ {$player->getName()}さんを釈放しました!");//コマンド実行者にメッセージ送信
						$player->sendMessage("[乙] " . $this->setting->get("otu.cmdm.otu.arasi.off"));//ターゲットへのめっせーじ
					}
				}else{
					if(!$this->otu->exists($args[0])){//otuされてるかを確認!
						$this->otu->set($args[0],"true");//otuリストに追加!
						$this->otu->save();//セーブ
						$sender->sendMessage("[乙] ( ﾟω^ )ゝ " . $args[0] . "さんをotuにリストに追加しました!");//コマンド実行者にメッセージ
					}else{//セットされていれば以下の処理
						$this->otu->remove($args[0]);//otuリストから削除
						$this->otu->save();//セーブ
						$sender->sendMessage("[乙] ( ﾟω^ )ゝ " . $args[0] . "さんを釈放しました!");//コマンド実行者にメッセージ送信
					}
				}
				return true;
			break;
			case "otup"://otupコマンド実行時の処理
				if(!($sender instanceof Player)){$sender->sendMessage("[乙] ゲーム内で実行してください");}
				$x = round($sender->getX(), 1);//コマンド実行者のX座標取得&四捨五入
				$y = round($sender->getY(), 1);//コマンド実行者のY座標取得&四捨五入
				$z = round($sender->getZ(), 1);//コマンド実行者のZ座標取得&四捨五入
				$this->config->set("xyz",$x . "," . $y ."," . $z . "," . $sender->getLevel()->getName());//設定ファイルに座標を設定
				$this->config->save();//セーブ
				$this->level = $sender->getLevel();
				//コマンド実行者へ設定完了メッセージを送信
				$sender->sendMessage("[乙] 牢屋の座標を x:" . $x . " y:" . $y . " z:" . $z . "に設定しました");
				return true;
			break;
			case "runa"://runaコマンド実行時の処理
				if(!isset($args[0])){return false;}//例外回避
				$player = $this->getServer()->getPlayer($args[0]);//プレーヤー名取得
				if($player instanceof Player){//プレーヤーが存在するかをチェック
					if(!$this->otu->exists($player->getName())){//outされてるかを確認!
						$sender->sendMessage("[乙] otuされていません");//コマンド実行者にメッセージ送信
					}else{//セットされていれば以下の処理
						if($this->otu->get($player->getName()) == "blocked"){//blockedになってるか
							$this->otu->set($player->getName(),"true");//runaリストから削除
							$this->otu->save();//セーブ
							$sender->sendMessage("[乙] ( ﾟω^ )ゝ {$player->getName()}さんをrunaリストから削除しました!");//コマンド実行者にメッセージ送信
							$player->sendMessage("[乙] " . $this->setting->get("otu.cmdm.runa.arasi.off"));//ターゲットへのめっせーじ
						}else{//なっていなければ以下の処理
							$this->otu->set($player->getName(),"blocked");//ルナ判定になるように値を変更
							$this->otu->save();//セーブ
							$sender->sendMessage("[乙] ( ﾟω^ )ゝ " . $player->getName() . "さんを動けなくしました");//コマンド実行者にメッセージ
							$player->sendMessage("[乙] " . $this->setting->get("otu.cmdm.runa.arasi.now"));//ターゲットへのめっせーじ
						}
					}
				}else{
					$sender->sendMessage("[乙] プレーヤーが存在しません");//コマンド実行者にメッセージ送信
				}
				return true;
			break;
			case "otulist"://otulistコマンド実行時の処理
				$otulist = $this->otu->getAll();//otuリストを配列で取得
				if(count($otulist) == 0){//otuリストの配列の数を取得し配列があるかをチェック
					$sender->sendMessage("[乙] 現在乙された人はいません");//コマンド実行者にメッセージ送信
					return true;
				}
				$list = "---otu&runaリスト---\n";//最初のメッセージ
				$count = 0;
				foreach($otulist as $key => $value){//取得したotuリストの配列からキーと値を取得しループ
					//取得した値からルナか乙かを判定しルナ、乙表記を付ける//(値がblockedの場合はルナ、それ以外の場合は乙と表示されます)
					$oturuna = ($value == "blocked") ? "ルナ" : "乙";
					//count変数が3以上であれば改行して表示を見やすく
					if($count >= 3){//$countが3より上か
						//リストに追加
						$list .= $key . "(" . $oturuna . "),\n";
						$count = 0;//count変数をリセット//0に
					}else{
						//リストに追加
						$list .= $key . "(" . $oturuna . "),";
						++$count;//count変数に1を足す
					}
				}
				$list = trim($list, ',');//前後の,を削除
				$sender->sendMessage($list);//リストをメッセージ
				return true;
			break;
			case "jail"://jailコマンド実行時の処理
				if(!isset($args[0])){return false;}//例外回避
				if(!isset($args[1])){$args[1] = null;}//例外回避
				$player = $this->getServer()->getPlayer($args[0]);//プレーヤー名取得
				if($player instanceof Player){//プレーヤーが存在するかをチェック
					jail::getInstance()->playerJail($player,$sender,$args[1]);//jail.phpで処理!
					//コマンド送信者がプレーヤーかを判定し顔文字を見やすく//どうでもいいこだわりw
					if($sender instanceof Player){
						$sender->sendMessage("[乙] ( ｀･ω ･´)ゞ " . $player->getName() . "さんを牢屋に入れました!");//コマンド実行者にメッセージ
					}else{
						$sender->sendMessage("[乙] (｀･ω･´)ゞ " . $player->getName() . "さんを牢屋に入れました!");//コマンド実行者にメッセージ
					}
					$player->sendMessage("[乙] " . $this->setting->get("otu.cmdm.jail.arasi.now"));//ターゲットへのめっせーじ
				}else{
					$sender->sendMessage("[乙] プレーヤーが存在しません");//コマンド実行者にメッセージ送信
				}
				return true;
			break;
			case "unjail"://jailコマンド実行時の処理
				if(jail::getInstance()->unJail($sender->getName())){//jail.phpで処理
					//コマンド送信者がプレーヤーかを判定し顔文字を見やすく//どうでもいいこだわりw
					if($sender instanceof Player){
						$sender->sendMessage("[乙] ( ｀･ω ･´)ゞ 牢屋を撤去しました");//コマンド実行者にメッセージ
					}else{
						$sender->sendMessage("[乙] (｀･ω･´)ゞ 牢屋を撤去しました");//コマンド実行者にメッセージ
					}
				}else{
					$sender->sendMessage("[乙] 戻すためのデータがありません");//コマンド実行者にメッセージ送信
				}
				return true;
			break;
			case "jailcraft"://jailコマンド実行時の処理
				if(!isset($args[0])){return false;}//例外回避
				switch ($args[0]) {
				case "pos1":
				case "p1":
					$this->jpt[$sender->getName()] = 1;
					$sender->sendMessage("[乙] ブロックタッチ!");//コマンド実行者にメッセージ送信
					break;
				case "pos2":
				case "p2":
					$this->jpt[$sender->getName()] = 2;
					$sender->sendMessage("[乙] ブロックタッチ!");//コマンド実行者にメッセージ送信
					break;
				case "pos3":
				case "p3":
					Jail::getInstance()->pos[$sender->getName()][3] = array("x" => $sender->getX(), "y" => $sender->getY(), "z" => $sender->getZ());
					//todo ブロックタッチ
					$sender->sendMessage("[乙] プレーヤーの場所を設定しました");//コマンド実行者にメッセージ送信
					break;
				case "craft":
				case "c":
					if(!isset($args[1])){return false;}//例外回避
					//めんどくさいのでほぼ一回ですませる
					if(isset(Jail::getInstance()->pos[$sender->getName()][1]) and //pos1が指定されてるか
						isset(Jail::getInstance()->pos[$sender->getName()][2]) and //pos2が指定されてるか
							isset(Jail::getInstance()->pos[$sender->getName()][3]) and //pos3が指定されてるか
								isset($args[1])){//名前が指定されているか
						if($args[1][0] == "@"){$sender->sendMessage("[乙] 名前の最初に@を付けることはできません");return true;}//名前の最初に@がついてるか。ついていればOUT!
						if(Jail::getInstance()->craftJail($sender,$args[1])){
							$sender->sendMessage("[乙] 作成完了!");//コマンド実行者にメッセージ送信
						}else{
							$sender->sendMessage("[乙] 同じ名前の牢屋が既にあります");//コマンド実行者にメッセージ送信
						}
					}else{
						$sender->sendMessage("[乙] 始点と終点とプレーヤの場所と牢屋の名前を指定してください");//コマンド実行者にメッセージ送信
					}
					break;
				default:
					$sender->sendMessage("[乙] /jailcraft pos1:始点の指定");//コマンド実行者にメッセージ送信
					$sender->sendMessage("[乙] /jailcraft pos2:終点の指定");//コマンド実行者にメッセージ送信
					$sender->sendMessage("[乙] /jailcraft pos3:プレーヤーの位置を指定");//コマンド実行者にメッセージ送信
					$sender->sendMessage("[乙] /jailcraft craft <牢屋の名前>");//コマンド実行者にメッセージ送信
				}
				return true;
			break;
			case "unjailall"://unjailallコマンド実行時の処理
				if(jail::getInstance()->unJailAll()){//jail.phpで処理
					//コマンド送信者がプレーヤーかを判定し顔文字を見やすく//どうでもいいこだわりw
					if($sender instanceof Player){
						$sender->sendMessage("[乙] ( ｀･ω ･´)ゞ すべての牢屋を撤去しました");//コマンド実行者にメッセージ
					}else{
						$sender->sendMessage("[乙] (｀･ω･´)ゞ すべての牢屋を撤去しました");//コマンド実行者にメッセージ
					}
				}else{
					$sender->sendMessage("[乙] 戻すためのデータがありませんでした");//コマンド実行者にメッセージ送信
				}
				return true;
			break;
		}
		return false;
	}
	
	//コマンド制限
	public function onPlayerCommand(PlayerCommandPreprocessEvent $event){
		$player = $event->getPlayer();
		if($this->otu->exists($player->getName())){
			$m = $event->getMessage();
			if($m[0] == "/"){
				$sr = strpos($m, '/register');//ログインコマンドの場合はコマンドの使用を許可する
				$sl = strpos($m, '/login');
				if($sr === false and $sl === false){
					$event->setCancelled();
				}
			}
		}
	}
	
	//移動制限
	public function onPlayerMove(PlayerMoveEvent $event){
		$player = $event->getPlayer();
		if($this->otu->exists($player->getName())){
			if($this->otu->get($player->getName()) == "blocked"){
				$event->setCancelled();
			}
		}
	}
	
	//ブロックタッチ制限//とjailの範囲指定
	public function onPlayerInteract(PlayerInteractEvent $event){
		$player = $event->getPlayer();
		if($this->otu->exists($player->getName())){
			$event->setCancelled();
		}
		if(isset($this->jpt[$player->getName()])){
			$block = $event->getBlock();
			switch ($this->jpt[$player->getName()]) {
				case 1:
					jail::getInstance()->pos[$player->getName()][1] = array("x" => $block->getX(), "y" => $block->getY(), "z" => $block->getZ());
					unset($this->jpt[$player->getName()]);
					$player->sendMessage("[乙] 始点を指定しました");
					break;
				case 2:
					Jail::getInstance()->pos[$player->getName()][2] = array("x" => $block->getX(), "y" => $block->getY(), "z" => $block->getZ());
					unset($this->jpt[$player->getName()]);
					$player->sendMessage("[乙] 終点を指定しました");
					break;
			}
		}
    }
	
	//ブロック破壊制限
	public function onBlockBreak(BlockBreakEvent $event){
		$player = $event->getPlayer();
		if($this->otu->exists($player->getName())){
			$event->setCancelled();
		}
	}

	//ブロック設置制限
	public function onBlockPlace(BlockPlaceEvent $event){
		$player = $event->getPlayer();
		if($this->otu->exists($player->getName())){
			$event->setCancelled();
		}
	}
	
	//コマンドのパラメーターを値に変換し実行する
	public function commandParameterExchange(string $cmd, Player $p, Player $o){
		$param = [
			"%p",
			"%x",
			"%y",
			"%z",
			"cp",
			"cx",
			"cy",
			"cz"
		];
		$replace = [
			$p->getName(),
			$p->getX(),
			$p->getY(),
			$p->getZ(),
			$o->getName(),
			$o->getX(),
			$o->getY(),
			$o->getZ()
		];
		$cmd = str_replace($param, $replace, $cmd);
		if (strpos($cmd, '/') !== false) {
			$cmd = substr($cmd, 1);
		}
		$this->getServer()->dispatchCommand(new ConsoleCommandSender(), $cmd);
	}
}