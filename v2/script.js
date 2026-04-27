// Script para abrir o modal ao clicar nas imagens
const modal = document.getElementById('modal');
const modalImg = document.getElementById('modalImg');
const closeModal = document.querySelector('.close');

// Seleciona todas as imagens nas seções de serviços e galeria
const images = document.querySelectorAll('.servicos-grid img, .galeria-grid img');

images.forEach(img => {
    img.addEventListener('click', () => {
        modal.classList.add('active');
        modalImg.src = img.src;
    });
});

// Fecha o modal ao clicar no botão de fechar
closeModal.addEventListener('click', () => {
    modal.classList.remove('active');
});

// Fecha o modal ao clicar fora da imagem
modal.addEventListener('click', (e) => {
    if (e.target === modal) {
        modal.classList.remove('active');
    }
});

// Script para o formulário do WhatsApp
document.getElementById('whatsappForm').addEventListener('submit', function (e) {
    e.preventDefault(); // Impede o envio padrão do formulário

    // Captura os valores dos campos
    const nome = document.getElementById('nome').value;
    const mensagem = document.getElementById('mensagem').value;

    // Formata a mensagem para o WhatsApp
    const texto = `Olá, meu nome é ${nome}. Queria ver sobre ${mensagem}`;
    const url = `https://wa.me/556596762667?text=${encodeURIComponent(texto)}`;

    // Redireciona para o WhatsApp
    window.open(url, '_blank');
});

// Direciona para a seção de banner ao carregar a página
window.onload = function () {
    window.location.hash = "#home";
};

// Função para destacar o dia atual e o domingo
function destacarDias() {
    const diasDaSemana = ["domingo", "segunda", "terça", "quarta", "quinta", "sexta", "sábado"];
    const dataAtual = new Date();
    const diaAtual = diasDaSemana[dataAtual.getDay()]; // Obtém o nome do dia atual

    // Seleciona todos os dias da semana
    const dias = document.querySelectorAll('.dia');

    dias.forEach(dia => {
        const nomeDia = dia.querySelector('span').textContent.toLowerCase();

        // Destaca o dia atual
        if (nomeDia === diaAtual) {
            dia.classList.add('atual');
        }

        // Destaca o domingo com cor vermelha
        if (nomeDia === 'domingo') {
            dia.classList.add('fechado');
        }
    });
}

// Chama a função ao carregar a página
window.onload = function () {
    window.location.hash = "#home";
    destacarDias(); // Destaca o dia atual e o domingo
};