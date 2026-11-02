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

final class algoritm extends Page
{
    public function default(): Node|Response|null
    {
        $content = Builder::div('page-content');

        $el = Builder::h(1)->content('Architecting Tomorrow: Next-Generation Digital Solutions for Global Leaders');
        $content->append($el);

        $el = Builder::p()->content('In an era defined by rapid technological shifts and constant disruption, standing still is the same as moving backward. At our agency, we don\'t just write code; we engineer high-performance digital ecosystems that drive unprecedented growth, operational efficiency, and sustainable competitive advantage. We bridge the gap between complex business challenges and seamless, cutting-edge technological realities.');
        $content->append($el);

        $el = Builder::p()->content('Our approach is rooted in the philosophy of \'Innovation through Precision.\' We combine the agility of a modern startup with the rigorous engineering standards of an enterprise-grade powerhouse. Whether you are looking to disrupt a legacy industry or scale a digital-first product to millions of users, our team of elite architects, developers, and strategists is ready to build your future.');
        $content->append($el);

        $el = Builder::h(2)->content('Why Leading Enterprises Trust Our Digital Ecosystem');
        $content->append($el);

        $el = Builder::p()->content('We operate at the intersection of creativity and technical mastery. Our methodology is designed to minimize risk while maximizing speed-to-market, ensuring that every line of code serves a strategic business purpose. We don\'t just deliver a project; we deliver a scalable foundation for your long-term success.');
        $content->append($el);

        $el = Builder::ul();
        $content->append($el);

        $el = Builder::h(2)->content('Our Core Specializations');
        $content->append($el);

        $el = Builder::h(3)->content('Custom Software Engineering');
        $content->append($el);

        $el = Builder::p()->content('We design and deploy robust, scalable, and highly secure enterprise-grade applications tailored to your specific operational workflows. From complex microservices architectures to seamless mobile experiences, we ensure your software is built to perform under pressure.');
        $content->append($el);

        $el = Builder::a('/services')->content('');
        $el->attrs()->classAdd('btn');
        $content->append(Builder::p()->append($el));

        $el = Builder::h(3)->content('AI & Machine Learning Integration');
        $content->append($el);

        $el = Builder::p()->content('Unlock the hidden potential of your data. We integrate advanced machine learning models and neural networks into your existing workflows to automate complex decision-making, provide predictive analytics, and create truly intelligent user experiences.');
        $content->append($el);

        $el = Builder::a('/cases')->content('');
        $el->attrs()->classAdd('btn');
        $content->append(Builder::p()->append($el));

        $el = Builder::h(3)->content('Cloud & DevOps Transformation');
        $content->append($el);

        $el = Builder::p()->content('Accelerate your deployment cycles and optimize infrastructure costs. Our DevOps experts build automated CI/CD pipelines and manage multi-cloud environments, ensuring your digital assets are always available, secure, and infinitely scalable.');
        $content->append($el);

        $el = Builder::a('/contacts')->content('');
        $el->attrs()->classAdd('btn');
        $content->append(Builder::p()->append($el));

        $el = Builder::h(2)->content('Ready to Transform Your Digital Presence?');
        $content->append($el);

        $el = Builder::p()->content('Your vision deserves more than just a vendor; it deserves a strategic partner dedicated to technical excellence. Let\'s discuss how our digital solutions can propel your business into the next decade of innovation. Our experts are standing by to turn your most ambitious ideas into reality.');
        $content->append($el);

        $el = Builder::a('/contacts')->content('');
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
