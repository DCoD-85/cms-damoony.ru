<?php

declare(strict_types=1);

namespace damoony_ru\coffee;

use Damoony\Algoritm\Page;
use Damoony\Ui\Builder;
use Damoony\Ui\Node;
use Damoony\Http\Response;
use Damoony\Files\Paths;
use Damoony\Scheme\SchemeLoader;
use Damoony\Scheme\SchemeRenderer;

final class algoritm_services extends Page
{
    public function default(): Node|Response|null
    {
        $content = Builder::div('page-content');

        $el = Builder::h(1)->content('Architecting the Future: Premium IT Solutions for the Digital Age');
        $content->append($el);

        $el = Builder::p()->content('In an era defined by rapid technological shifts, staying ahead requires more than just adaptation—it requires total digital transformation. Our agency specializes in bridging the gap between complex business challenges and elegant, high-performance digital solutions. We don\'t just write code; we engineer robust ecosystems that drive growth, security, and unparalleled user experiences. From the initial spark of a concept to the deployment of massive-scale cloud architectures, our team of elite engineers and designers works tirelessly to ensure your business remains at the cutting edge of the digital frontier. We embrace the dark aesthetic of modern high-tech, focusing on precision, speed, and neon-bright innovation to deliver results that matter.');
        $content->append($el);

        $el = Builder::img('/content/damoony.ru/coffee/services/2b36cd018b372341.jpg', 'Image');
        $content->append($el);

        $el = Builder::h(2)->content('Our Specialized Service Suite');
        $content->append($el);

        $el = Builder::p()->content('We offer a curated selection of high-impact services designed to propel your digital presence into the next decade. Our expertise is not spread thin; it is concentrated in the areas that matter most for modern enterprise scalability, security, and operational excellence.');
        $content->append($el);

        $el = Builder::h(3)->content('Custom Software Engineering');
        $content->append($el);

        $el = Builder::p()->content('We build robust, scalable, and highly intuitive software applications tailored to your unique business logic. Our developers utilize the latest tech stacks—from React and Node.js to Python and Go—to ensure your product is fast, secure, and ready for global scale. We focus on clean code and modular architecture.');
        $content->append($el);

        $el = Builder::a('/cases')->content('View Our Cases');
        $el->attrs()->classAdd('btn');
        $content->append(Builder::p()->append($el));

        $el = Builder::h(3)->content('Cloud Infrastructure & DevOps');
        $content->append($el);

        $el = Builder::p()->content('Optimize your operational efficiency with automated CI/CD pipelines and cloud-native architectures. We specialize in AWS, Azure, and Google Cloud, helping you migrate legacy workloads to the cloud while ensuring maximum uptime, seamless scaling, and significant cost-efficiency through intelligent resource management.');
        $content->append($el);

        $el = Builder::a('/contacts')->content('Get Started');
        $el->attrs()->classAdd('btn');
        $content->append(Builder::p()->append($el));

        $el = Builder::h(3)->content('Cybersecurity & Data Integrity');
        $content->append($el);

        $el = Builder::p()->content('In a landscape of evolving threats, we provide the shield your business needs. Our security audits, penetration testing, and zero-trust implementation strategies ensure that your data, your users, and your reputation remain impenetrable against even the most sophisticated cyber attacks.');
        $content->append($el);

        $el = Builder::a('/contacts')->content('Contact Us');
        $el->attrs()->classAdd('btn');
        $content->append(Builder::p()->append($el));

        $el = Builder::h(2)->content('Why Partner With Us?');
        $content->append($el);

        $el = Builder::ul();
        $el->append(Builder::li()->content('Agile-first methodology for rapid iteration'));
        $el->append(Builder::li()->content('Deep expertise in emerging technologies like AI and Blockchain'));
        $el->append(Builder::li()->content('Dedicated 24/7 technical support and maintenance'));
        $el->append(Builder::li()->content('Scalable and resilient architecture design'));
        $content->append($el);

        $el = Builder::p()->content('Our approach is rooted in transparency, technical excellence, and a commitment to your long-term success. We don\'t just deliver a project and walk away; we build a lasting partnership. By integrating deeply with your internal teams, we understand your unique vision and translate it into a digital reality that exceeds even the most ambitious expectations. Whether you are looking to modernize legacy systems or build something entirely new, we are here to guide you through every step of the development lifecycle. Explore our portfolio to see how we\'ve helped industry leaders transform their operations and dominate their respective markets.');
        $content->append($el);

        $el = Builder::a('/cases')->content('Explore our recent success stories');
        $content->append(Builder::p()->append($el));

        $this->setContent('center', $content);

        $paths = $this->context->service('paths');
        if (!$paths instanceof Paths) {
            return Response::internalError('Paths service missing');
        }

        $loader = new SchemeLoader($paths);
        $manifest = $loader->load($this->context->domain, $this->context->domain->scheme);
        $renderer = new SchemeRenderer();

        return $renderer->render($manifest, $this->regions(), $this->context);
    }
}
