import styled from 'styled-components/macro';
import tw from 'twin.macro';

export default styled.div<{ $hoverable?: boolean }>`
    ${tw`flex rounded-lg no-underline text-neutral-50 items-center bg-neutral-700 p-4 border border-neutral-600 transition-all duration-150 overflow-hidden shadow-sm`};

    ${(props) => props.$hoverable !== false && tw`hover:border-primary-500 hover:shadow-[0_0_15px_rgba(34,197,94,0.15)]`};

    & .icon {
        ${tw`rounded-full w-16 flex items-center justify-center bg-neutral-800 text-primary-400 p-3`};
    }
`;
