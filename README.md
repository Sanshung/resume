# resume
Код для резюме

import/index.php - принмает json фаилы из 1С  
import/update.php - выгружает данные в промежуточную базу и выгружает только изменинения   
use Classes\Importer; - класс для работы с импортом  

Пример работы интеграции с Moisklad  

namespace Classes\Moisklad;

Counterparty - выгружает контрагентов   
GetDemand - обновления корзины заказа    
GetOrders - обновления заказов    
ExportController - вспомогательный класс  
Request - класс отправки запросов
