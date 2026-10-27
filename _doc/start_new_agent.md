ПРоект: Damoony
- Гит проекта: https://github.com/DCoD-85/cms-damoony.ru/
- локальный сервер:
	Noutbook; Intel core I9 (14900hx);GeForce4060(8GB);32GBRAM;SamsungPro990(SSD)2TB+1TB;
	Win11;
	ospanel543;
		php8.4.25; (E:\OSPanel543\modules\php\PHP_8.4)
		mariadb-11.8; (E:\OSPanel543\modules\database\MariaDB-11.8-Win10\bin)
	папка проекта: E:\OSPanel543\domains\damoony3.ru\
	- разрабатываем локально через gitbash (vscode)
	- делаем регулярную синхронизацию с ГИТ
- Боевой сервер:
	i7;GeForce1060(4GB);8GB;128(SSD)+1TB(HDD)
	Debian13;
	Apache2
		php8.4.25;
		mariadb-11.8;
	папка проекта: /var/www/damoony.ru/
	- локально не вмешиваемся в работу. Только через загрузку через гит