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

final class algoritm_contacts extends Page
{
    public function default(): Node|Response|null
    {
        $content = Builder::div('page-content');

        $el = Builder::h(1)->content('Connect with the Architects of Tomorrow');
        $content->append($el);

        $el = Builder::p()->content('At our agency, we don\'t just build software; we engineer digital ecosystems that redefine industry standards. Whether you are a disruptive startup looking to capture market share or a global enterprise seeking to modernize legacy systems, our mission is to bridge the gap between complex technical challenges and seamless, high-performance user experiences. Our high-tech approach combines cutting-edge AI, robust cloud infrastructure, and sleek, neon-accented design to ensure your brand stands out in a crowded digital landscape. We thrive on complexity and excel at turning abstract ideas into scalable, production-ready realities.');
        $content->append($el);

        $el = Builder::img('/content/damoony.ru/coffee/contacts/9e2e8cb4acd40553.jpg', 'Image');
        $content->append($el);

        $el = Builder::h(2)->content('Ready to Scale Your Digital Infrastructure?');
        $content->append($el);

        $el = Builder::p()->content('The path to digital dominance starts with a single strategic conversation. We invite you to reach out and discuss how our specialized engineering teams can accelerate your product roadmap. From initial discovery sessions and architectural design to full-scale deployment and continuous DevOps support, we provide the technical backbone your business needs to thrive in the modern era. We don\'t just act as a vendor; we act as your dedicated technical partner, deeply invested in your long-term success and scalability.');
        $content->append($el);

        $el = Builder::h(3)->content('Direct Communication Channels');
        $content->append($el);

        $el = Builder::ul();
        $content->append($el);

        $el = Builder::h(2)->content('Our Expertise at a Glance');
        $content->append($el);

        $el = Builder::p()->content('Our expertise is not limited to simple web development. We dive deep into the core of your business logic, optimizing every layer of your stack. Whether it\'s implementing zero-trust security protocols, building real-time data processing pipelines, or crafting immersive frontend interfaces, our engineers are equipped with the latest tools and methodologies. We focus on building resilient, future-proof systems that can handle the demands of tomorrow\'s users today.');
        $content->append($el);

        $el = Builder::h(2)->content('Why Partner With Us?');
        $content->append($el);

        $el = Builder::p()->content('Why choose our high-tech agency? Because we understand that in the digital age, speed and stability are non-negotiable. We leverage automated testing, continuous integration, and advanced cloud-native architectures to ensure that your deployment is seamless and your uptime is absolute. Our team stays ahead of the curve, constantly researching emerging technologies so that you don\'t have to. When you partner with us, you are gaining access to a powerhouse of innovation, ready to tackle your most ambitious technical goals.');
        $content->append($el);

        $el = Builder::a('#')->content('');
        $el->attrs()->classAdd('btn');
        $content->append(Builder::p()->append($el));

        $el = Builder::a('#')->content('');
        $el->attrs()->classAdd('btn');
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
