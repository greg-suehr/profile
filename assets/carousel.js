document.addEventListener('DOMContentLoaded', function() {
    
  const carousels = document.querySelectorAll('.carousel');
  carousels.forEach(function( carousel, index ) {
      const ele = carousel.querySelector('ul');
      const amountvisible = Math.round(ele.offsetWidth/ele.querySelector('li:nth-child(1)').offsetWidth);
      const slides = carousel.querySelectorAll('ul li');
      let isDown = false;
      let startX;
      let scrollLeft;
      let walk;
      let hasDragged = false;

      ele.id = 'carousel'+index;
      carousel.setAttribute('duration', 7000);

      const ol = document.createElement('ol');
      carousel.appendChild(ol);

      slides.forEach(function(slide, slideindex) {

        slide.id = 'c'+index+'_slide'+(slideindex+1);
        var elements = slide.querySelectorAll('img, button, .button, a');
        elements.forEach(function(element) {
          element.setAttribute('draggable','false');
        });

        slide.addEventListener('click', function(e) {
            if(hasDragged) {
              e.preventDefault();
              e.stopPropagation();
            }
          }, true);

        const li = document.createElement('li');
        li.innerHTML = `<a href="#c${index}_slide${slideindex+1}"><span class="sr-only">Slide ${slideindex+1}</span></a>`;
        ol.appendChild(li);
      });

      const nextbutton = document.createElement('button');
      nextbutton.classList.add('next');
      nextbutton.setAttribute('aria-label','Next Slide');
      nextbutton.innerHTML = `›`;
      carousel.appendChild(nextbutton);
      const prevbutton = document.createElement('button');
      prevbutton.classList.add('prev');
      prevbutton.setAttribute('aria-label','Previous Slide');
      prevbutton.innerHTML = `‹`;
      carousel.appendChild(prevbutton);
      const bullets = carousel.querySelectorAll('ol li');
      const nextarrow = carousel.querySelector('.next');
      const prevarrow = carousel.querySelector('.prev');
    // Initialize State
      ele.scrollLeft = 0;
      bullets[0].classList.add('selected');
      slides[0].classList.add('selected');
      if(amountvisible>1) {
        var removeels = carousel.querySelectorAll('ol li:nth-last-child(-n + '+(amountvisible-1)+')');
        removeels.forEach(function(removeel) {
          removeel.remove();
        });
      }
      const setSelected = function() {
          bullets.forEach(function(bullet) {
             bullet.classList.remove('selected');
          });
          slides.forEach(function(slide) {
             slide.classList.remove('selected');
          });
          const scrolllength = carousel.querySelector('ul li:nth-child(2)').offsetLeft - carousel.querySelector('ul li:nth-child(1)').offsetLeft;
          const nthchild = (Math.round((ele.scrollLeft/scrolllength)+1));
          carousel.querySelector('ol li:nth-child('+nthchild+')').classList.add('selected'); 
          carousel.querySelector('ul li:nth-child('+nthchild+')').classList.add('selected'); 
      }
      const scrollTo = function(event) {
          event.preventDefault();
          ele.scrollLeft = ele.querySelector(this.getAttribute('href')).offsetLeft;
      }
      
      const nextSlide = function() {
          if(!carousel.querySelector('ol li:last-child').classList.contains('selected')) {
              carousel.querySelector('ol li.selected').nextElementSibling.querySelector('a').click();
          } else {
              carousel.querySelector('ol li:first-child a').click();
          }
      }
      const prevSlide = function() {
          if(!carousel.querySelector('ol li:first-child').classList.contains('selected')) {
              carousel.querySelector('ol li.selected').previousElementSibling.querySelector('a').click();
          } else {
              carousel.querySelector('ol li:last-child a').click();
          }
      }
      
      const setInteracted = function() {
        ele.classList.add('interacted');
      }
          

      ele.addEventListener("scroll", debounce(setSelected));
      ele.addEventListener("touchstart", setInteracted);
      ele.addEventListener('keydown', function (e){
          if(e.key == 'ArrowLeft') ele.classList.add('interacted');
          if(e.key == 'ArrowRight') ele.classList.add('interacted');
      });
      const startDrag = function(e) {
          isDown = true;
          hasDragged = false;
          ele.classList.add('grabbing');
          ele.style.scrollSnapType = 'none';
          ele.focus();
          startX = e.pageX - ele.offsetLeft;
          scrollLeft = ele.scrollLeft;
          ele.classList.add('interacted');
      };
      const stopDrag = function(e) {
          ele.classList.remove('grabbing');

          const scrolllength = carousel.querySelector('ul li:nth-child(2)').offsetLeft - carousel.querySelector('ul li:nth-child(1)').offsetLeft;
          const round = ((ele.scrollLeft/scrolllength)+1)%1;
          let roundup = false;
          if(Math.abs(round)>=0.5) {
              roundup = true
          }
          if(isDown && walk<-10 && hasDragged) {
            if(!carousel.querySelector('ol li.selected:last-child') && roundup==false) {
              carousel.querySelector('.next').click();
            } else {
              carousel.querySelector('ol li.selected a').click();
            }
          }
          if(isDown && walk>10 && hasDragged) {
            if(!carousel.querySelector('ol li.selected:first-child') && roundup==true) {
              carousel.querySelector('.prev').click();
            } else {
              carousel.querySelector('ol li.selected a').click();
            }
          }
          
          isDown = false;
          ele.style.scrollSnapType = 'x mandatory';
      };  
      const mouseMoveDrag = function(e) {
          if(!isDown) return;
          e.preventDefault();
          const x = e.pageX - ele.offsetLeft;
          walk = (x - startX) * 1; //scroll-fast
          if(Math.abs(walk) > 5) {
              hasDragged = true;
          }
          ele.scrollLeft = scrollLeft - walk;
      };
      ele.addEventListener('mousedown', startDrag, false);
      ele.addEventListener('mouseleave', stopDrag, false);
      ele.addEventListener('mouseup', stopDrag, false);
      ele.addEventListener('mousemove', debounce(mouseMoveDrag), false);
      nextarrow.addEventListener("click", nextSlide);
      nextarrow.addEventListener("mousedown", setInteracted);
      nextarrow.addEventListener("touchstart", setInteracted);
      prevarrow.addEventListener("click", prevSlide);
      prevarrow.addEventListener("mousedown", setInteracted);
      prevarrow.addEventListener("touchstart", setInteracted);
      bullets.forEach(function(bullet) {
        bullet.querySelector('a').addEventListener('click', scrollTo);
        bullet.addEventListener("mousedown", setInteracted);
        bullet.addEventListener("touchstart", setInteracted);
      });
      //setInterval for autoplay
      if(carousel.getAttribute('duration')) {
        setInterval(function(){ 
          if (ele.classList.contains('interacted')==false) {
            nextarrow.click();
          }
        }, carousel.getAttribute('duration'));
      }
    
    
  }); //end foreach
}); //end onload
function debounce (fn) {
  let timeout;
  return function () {
    let context = this;
    let args = arguments;

    if (timeout) {
      window.cancelAnimationFrame(timeout);
    }

    timeout = window.requestAnimationFrame(function () {
      fn.apply(context, args);
    });
  };
}
