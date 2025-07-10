document.addEventListener('DOMContentLoaded', function() {
    // Находим все элементы аккордеона
    var accordionItems = document.querySelectorAll('.accordion-item');
    var accordionHeaders = document.querySelectorAll('.accordion-header');
    
    // Функция для открытия/закрытия элемента аккордеона
    function toggleAccordion(item) {
        // Проверяем, активен ли элемент
        var isActive = item.classList.contains('active');
        
        // Переключаем состояние элемента
        if (isActive) {
            // Если элемент был активен, закрываем его
            item.classList.remove('active');
            var content = item.querySelector('.accordion-content');
            if (content) {
                content.style.display = 'none';
            }
        } else {
            // Если элемент не был активен, открываем его
            item.classList.add('active');
            var content = item.querySelector('.accordion-content');
            if (content) {
                content.style.display = 'block';
            }
        }
    }
    
    // Добавляем обработчик клика для каждого заголовка
    accordionHeaders.forEach(function(header) {
        header.addEventListener('click', function(e) {
            // Предотвращаем обработку клика родительскими элементами
            e.preventDefault();
            
            // Получаем родительский элемент (accordion-item)
            var parentItem = this.parentNode;
            
            // Открываем или закрываем элемент
            toggleAccordion(parentItem);
        });
    });
    
    // По умолчанию открываем все элементы
    accordionItems.forEach(function(item) {
        item.classList.add('active');
        var content = item.querySelector('.accordion-content');
        if (content) {
            content.style.display = 'block';
        }
    });
});
