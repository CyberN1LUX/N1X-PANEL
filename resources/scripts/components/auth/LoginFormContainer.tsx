import React, { forwardRef } from 'react';
import { Form } from 'formik';
import styled from 'styled-components/macro';
import { breakpoint } from '@/theme';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
};

const Container = styled.div`
    ${breakpoint('sm')`
        ${tw`w-4/5 mx-auto`}
    `};

    ${breakpoint('md')`
        ${tw`p-10`}
    `};

    ${breakpoint('lg')`
        ${tw`w-3/5`}
    `};

    ${breakpoint('xl')`
        ${tw`w-full`}
        max-width: 700px;
    `};
`;

export default forwardRef<HTMLFormElement, Props>(({ title, ...props }, ref) => (
    <Container>
        {title && <h2 css={tw`text-3xl text-center text-neutral-100 font-medium py-4`}>{title}</h2>}
        <FlashMessageRender css={tw`mb-2 px-1`} />
        <Form {...props} ref={ref}>
            <div css={tw`md:flex w-full bg-neutral-700 border border-neutral-600 shadow-2xl rounded-lg p-6 md:pl-0 mx-1`}>
                <div css={tw`flex-none select-none mb-6 md:mb-0 self-center`}>
                    <img src={'/assets/svgs/n1x-logo.svg'} css={tw`block w-48 md:w-56 mx-auto`} alt={'N1X PANEL'} />
                </div>
                <div css={tw`flex-1`}>{props.children}</div>
            </div>
        </Form>
        <p css={tw`text-center text-neutral-400 text-xs mt-4`}>
            &copy; 2026&nbsp;
            <a
                rel={'noopener nofollow noreferrer'}
                href={'/'}
                css={tw`no-underline text-primary-400 hover:text-primary-300 font-semibold`}
            >
                N1X PANEL
            </a>
            &nbsp;&mdash; Multi-Language Hosting Platform
        </p>
    </Container>
));
